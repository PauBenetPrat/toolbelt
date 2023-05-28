<?php


namespace App\Services;

use App\Exceptions\GitClientOnlySyncException;

class GitClient
{

    readonly public string $username;
    readonly public string $repository;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $remoteUrlOutput = exec('git remote get-url origin');
        $regex = '/bitbucket.org[:\/](.*)\/(.*).git/';

        if (!preg_match($regex, $remoteUrlOutput, $matches)) {
            throw new \Exception("Failed to extract username and repository from the remote URL: $remoteUrlOutput");
        }
        $this->username = $matches[1];
        $this->repository = $matches[2];

    }

    public function mergeCommitsBetween(string $mainBranch, string $releaseBranch): array
    {
        exec("git log {$mainBranch}..{$releaseBranch} --merges --pretty=format:\"%H\"", $output);
        return $output;
    }

    /**
     * @throws GitClientOnlySyncException
     */
    public function prNumberFromMergeCommit(string $mergeCommit): string
    {
        $commitDescription = exec("git show --no-patch --pretty=format:%s $mergeCommit");
        preg_match('/#[0-9]+/', $commitDescription, $matches);
        if (count($matches) === 0) {
            throw new GitClientOnlySyncException($commitDescription);
        }
        return ltrim($matches[0], '#');
    }
}
