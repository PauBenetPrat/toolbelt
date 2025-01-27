<?php


namespace App\Services\GitClients;

use App\Exceptions\GitClientOnlySyncException;
use App\Services\GitClient;

class BitbucketGitClient extends GitClient
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

        parent::__construct($matches[1], $matches[2]);
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
