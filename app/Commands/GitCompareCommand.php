<?php

namespace App\Commands;

use App\Exceptions\GitClientOnlySyncException;
use App\Exceptions\RepositoryException;
use App\Services\GitClient;
use App\Services\GitClients\AzureGitClient;
use App\Services\GitClients\BitbucketGitClient;
use App\Services\Repository;
use App\Services\RepositoryClients\AzureRepository;
use App\Services\RepositoryClients\BitbucketRepository;
use LaravelZero\Framework\Commands\Command;

class GitCompareCommand extends Command
{
    protected $signature = 'git-compare
        {--S|skip-api-calls : Skip API calls and only print the PR links}
        {--O|open-on-browser : Open all links in the browser}
        {--no-fetch : Don\'t fetch origins}
        {--limit= : Limit the number of results}
        {--skip= : Skip a certain number of results}
        {release-branch-or-tag=dev}
        {main-branch=revo}
    ';

    protected $description = 'Retrieve a list of pull requests between two branches in a Git repository and optionally retrieve additional information from the Bitbucket API, including its linear links.';

    private GitClient $gitClient;
    private Repository $repositoryClient;
    private bool $onAzure;

    public function handle()
    {
        $releaseBranch = $this->argument('release-branch-or-tag');
        $mainBranch = $this->argument('main-branch');

        $remoteUrlOutput = exec('git remote get-url origin');
        $this->onAzure = str_contains($remoteUrlOutput, 'ssh.dev.azure.com');
        try {
            $this->gitClient = $this->onAzure
                ? app(AzureGitClient::class, ['remoteUrlOutput' => $remoteUrlOutput])
                : app(BitbucketGitClient::class, ['remoteUrlOutput' => $remoteUrlOutput]);
        } catch (\Exception $e)  {
            $this->error($e->getMessage());
            exit(1);
        }
        $this->repositoryClient = $this->onAzure ?
            new AzureRepository($this->gitClient) :
            new BitbucketRepository($this->gitClient);

        if (!$this->onAzure) {
            $this->checkForBitbucketBearer();
        }

        $this->preFetchBranches($mainBranch, $releaseBranch);

        $mergeCommits = $this->gitClient->mergeCommitsBetween($mainBranch, $releaseBranch);
        if ($skip = $this->option('skip')) {
            $mergeCommits = array_slice($mergeCommits, $skip);
        }

        if ($limit = $this->option('limit')) {
            $mergeCommits = array_slice($mergeCommits, 0, $limit);
        }

        collect($mergeCommits)->each(function (string $mergeCommit) {
            try {
                $prNumber = $this->gitClient->prNumberFromMergeCommit($mergeCommit);
            } catch (GitClientOnlySyncException $e) {
                $this->info("SYNC PR: {$e->getMessage()}");
                return;
            }
            $link = $this->getLink($prNumber);

            if ($this->option('open-on-browser')) {
                exec("open \"$link\"");
            }
        });
    }

    protected function checkForBitbucketBearer()
    {
        if ($this->option('skip-api-calls')) {
            return;
        }

        if ($this->repositoryClient->bearer) {
            return;
        }

        $this->alert("Consider setting your BITBUCKET_API_TOKEN in the .env file.");

        $this->repositoryClient->bearer = $this->ask('Enter your Bitbucket API token to get linears output or press enter to continue');
        if ($this->repositoryClient->bearer) {
            $this->comment("RUN: echo BITBUCKET_API_TOKEN=\"{$this->repositoryClient->bearer}\" >> .env");
        } else {
            $this->warn("Bearer not set. Only PR links will be printed; no PR information will be fetched.");
        }
    }

    protected function preFetchBranches(string $mainBranch, string $releaseBranch): void
    {
        if ($this->option('no-fetch')) {
            return;
        }

        $this->info("Getting `{$releaseBranch}` to `{$mainBranch}` diff from {$this->gitClient->username}/{$this->gitClient->repository}");
        $this->repositoryClient->preFetch($mainBranch);
        $this->repositoryClient->preFetch($releaseBranch);
        $this->repositoryClient->fetch($mainBranch);
        $this->repositoryClient->fetch($releaseBranch);
    }

    protected function getLink(string $prNumber): ?string
    {
        if ($this->onAzure || $this->option('skip-api-calls') || !$this->repositoryClient->bearer) {
            $link = $this->repositoryClient->prLink($prNumber);
            $this->info("Repository: $link");
            return $link;
        }

        try {
            [$linearLink, $prTitle, $prDescription] = $this->repositoryClient->getPRInfo($prNumber);
        } catch (RepositoryException $e) {
            $this->error("Bitbucket API error - {$e->getMessage()}");
            exit(1);
        }

        if (!$linearLink) {
            $link = $this->repositoryClient->prLink($prNumber);
            $this->warn("No Linear found at {$link} - {$prTitle}");
            return $link;
        }

        $this->info("LINEAR: {$linearLink}");
        return $linearLink;
    }
}
