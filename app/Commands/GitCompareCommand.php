<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\Http;

class GitCompareCommand extends Command
{
    protected $signature = 'git-compare
        {--s : Skip API calls and only print the PR links}
        {--o : Open all links in the browser}
        {release-branch-or-tag=dev}
        {main-branch=revo}
        ';

    protected $description = 'Retrieve a list of pull requests between two branches in a Git repository and optionally retrieve additional information from the Bitbucket API and its linear links.';

    public function handle()
    {
        $releaseBranch = $this->argument('release-branch-or-tag');
        $mainBranch = $this->argument('main-branch');
        $openBrowser = $this->option('o');
        [$username, $repository] = $this->getUsernameAndRepositoryFromGit();

        $bearer = $this->getBearer($repository);

        $this->info("Getting \`$releaseBranch\` to \`$mainBranch\` diff from https://bitbucket.org:${username}/${repository}.git");

        $this->preFetch($mainBranch, $releaseBranch);

        collect($this->getMergeCommits($mainBranch, $releaseBranch))->each(function (string $mergeCommit) use (
            $repository,
            $username,
            $bearer,
            $openBrowser
        ) {
            if (! $prNumber = $this->prNumber($mergeCommit)) {
                return;
            }
            if (!$bearer) {
                $link = $this->getBitbucketLink($username, $repository, $prNumber);
                $this->info("BITBUCKET: $link");
            } else {
                [$link, $prTitle] = $this->getPrInfo($bearer, $username,
                    $repository, $prNumber);

                if (!empty($link)) {
                    $this->info("LINEAR: $link");
                } else {
                    $link = "https://bitbucket.org/{$username}/{$repository}/pull-requests/{$prNumber}";
                    $this->warn("WARNING: No Linear found at {$link} {$prTitle}");
                }
            }

            if ($openBrowser) {
                exec("open \"$link\"");
            }
        });
    }

    protected function getUsernameAndRepositoryFromGit()
    {
        // Extract username and repository from git remote URL
        $remoteUrlOutput = exec('git remote get-url origin');
        $regex = '/bitbucket.org[:\/](.*)\/(.*).git/';

        if (preg_match($regex, $remoteUrlOutput, $matches)) {
            $username = $matches[1];
            $repository = $matches[2];
        } else {
            $this->error("Failed to extract username and repository from remote URL: $remoteUrlOutput");
            exit(1);
        }
        return [$username, $repository];
    }

    protected function getBearer(mixed $repository): mixed
    {
        $repositoryEnv = str_replace('-', '_', strtolower($repository));
        $bearerEnvKey = strtoupper($repositoryEnv).'_BEARER';
        $bearer = env($bearerEnvKey);
        $skipApiCalls = $this->option('s');

        if (!$bearer && !$skipApiCalls) {
            $this->warn("WARNING: $bearerEnvKey environment variable not set. Only PR links will be printed, no PR information will be fetched. Add `$bearerEnvKey=<your-bitbucket-api-bearer>` to .bitbucket_api_bearers to enable API calls.\n");
        }

        if ($skipApiCalls) {
            $bearer = '';
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

    protected function getPrInfo(
        $bearer,
        mixed $username,
        mixed $repository,
        string $prNumber,
    ) {
        // Call Bitbucket API to retrieve pull request details
        $response = Http::withHeaders(['Authorization' => "Bearer $bearer"])
            ->get("https://api.bitbucket.org/2.0/repositories/{$username}/{$repository}/pullrequests/{$prNumber}")
            ->json();

        if (isset($response['error'])) {
            $this->error("ERROR: Bitbucket api error - {$response['error']['message']}");
            exit(1);
        }

        $link = preg_match('/(https:\/\/linear\.app\/revo\/issue\/REV-[0-9]+)/', $response['description'],
            $matches) ? $matches[1] : null;
        return [$link, $response['title']];
    }

    function getBitbucketLink(mixed $username, mixed $repository, string $prNumber): string
    {
        return "https://bitbucket.org/{$username}/{$repository}/pull-requests/{$prNumber}";
    }

    protected function preFetch(
        bool|array|string|null $mainBranch,
        bool|array|string|null $releaseBranch
    ): void {
        exec("git fetch origin $mainBranch:$mainBranch");
        exec("git fetch origin $releaseBranch:$releaseBranch");
    }

    protected function getMergeCommits(bool|array|string|null $mainBranch, bool|array|string|null $releaseBranch): array
    {
        exec("git log {$mainBranch}..{$releaseBranch} --merges --pretty=format:\"%H\"", $output);
        return $output;
    }
}
