<?php

namespace App\Commands;

use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;

class GitCompareCommand extends Command
{
    protected $signature = 'git-compare
        {--S|skip-api-calls : Skip API calls and only print the PR links}
        {--O|open-on-browser : Open all links in the browser}
        {--no-fetch : Don\'t fetch origins}
        {release-branch-or-tag=dev}
        {main-branch=revo}
    ';

    protected $description = 'Retrieve a list of pull requests between two branches in a Git repository and optionally retrieve additional information from the Bitbucket API, including its linear links.';

    protected ?string $bearer = null;
    protected string $username;
    protected string $repository;

    public function handle()
    {
        $releaseBranch = $this->argument('release-branch-or-tag');
        $mainBranch = $this->argument('main-branch');
        $this->setUsernameAndRepositoryFromGit();

        $this->bearer = $this->getBearer();

        $this->preFetch($mainBranch, $releaseBranch);

        collect($this->getMergeCommits($mainBranch, $releaseBranch))->each(function (string $mergeCommit) {
            $prNumber = $this->prNumber($mergeCommit);
            if (! $prNumber) {
                return;
            }
            $link = $this->getLink($prNumber);

            if ($this->option('open-on-browser')) {
                exec("open \"$link\"");
            }
        });
    }

    protected function setUsernameAndRepositoryFromGit()
    {
        // Extract username and repository from the git remote URL
        $remoteUrlOutput = exec('git remote get-url origin');
        $regex = '/bitbucket.org[:\/](.*)\/(.*).git/';

        if (preg_match($regex, $remoteUrlOutput, $matches)) {
            $this->username = $matches[1];
            $this->repository = $matches[2];
        } else {
            $this->error("Failed to extract username and repository from the remote URL: $remoteUrlOutput");
            exit(1);
        }
    }

    protected function getBearer(): ?string
    {
        if ($this->option('skip-api-calls')) {
            return null;
        }

        if ($bearer = env('BITBUCKET_API_TOKEN')) {
            return $bearer;
        }

        $this->alert("Consider setting your BITBUCKET_API_TOKEN in the .env file.");

        $bearer = $this->ask('Enter your Bitbucket API token to get linears output or press enter to continue');

        if ($bearer) {
            $this->comment("RUN: echo BITBUCKET_API_TOKEN=\"{$bearer}\" >> .env");
        } else {
            $this->warn("Bearer not set. Only PR links will be printed; no PR information will be fetched.");
        }
        return $bearer;
    }

    protected function prNumber(string $mergeCommit): string
    {
        $commitDescription = exec("git show --no-patch --pretty=format:%s $mergeCommit");
        preg_match('/#[0-9]+/', $commitDescription, $matches);
        if (count($matches) === 0) {
            $this->info("SYNC PR: {$commitDescription}");
            return '';
        }
        return ltrim($matches[0], '#');
    }

    function getBitbucketLink(string $prNumber): string
    {
        return "https://bitbucket.org/{$this->username}/{$this->repository}/pull-requests/{$prNumber}";
    }

    protected function preFetch(string $mainBranch, string $releaseBranch): void
    {
        if ($this->option('no-fetch')) {
            return;
        }

        $this->info("Getting `{$releaseBranch}` to `{$mainBranch}` diff from https://bitbucket.org:{$this->username}/{$this->repository}.git");
        exec("git fetch origin $mainBranch");
        exec("git fetch origin $releaseBranch");
    }

    protected function getMergeCommits(string $mainBranch, string $releaseBranch): array
    {
        exec("git log {$mainBranch}..{$releaseBranch} --merges --pretty=format:\"%H\"", $output);
        return $output;
    }

    protected function getLink(string $prNumber): ?string
    {
        if (!$this->bearer) {
            $link = $this->getBitbucketLink($prNumber);
            $this->info("BITBUCKET: $link");
            return $link;
        }

        [$linearLink, $prTitle] = $this->getPrInfo($this->bearer, $prNumber);
        if (!$linearLink) {
            $link = "https://bitbucket.org/{$this->username}/{$this->repository}/pull-requests/{$prNumber}";
            $this->warn("No Linear found at {$link} - {$prTitle}");
            return $link;
        }

        $this->info("LINEAR: {$linearLink}");
        return $linearLink;
    }

    protected function getPrInfo(string $bearer, string $prNumber): array
    {
        // Call the Bitbucket API to retrieve pull request details
        $response = Http::withHeaders(['Authorization' => "Bearer $bearer"])
            ->get("https://api.bitbucket.org/2.0/repositories/{$this->username}/{$this->repository}/pullrequests/{$prNumber}")
            ->json();

        if (isset($response['error'])) {
            $this->error("Bitbucket API error - {$response['error']['message']}");
            exit(1);
        }

        $link = preg_match('/(https:\/\/linear\.app\/revo\/issue\/REV-[0-9]+)/', $response['description'], $matches) ? $matches[1] : null;
        return [$link, $response['title']];
    }
}
