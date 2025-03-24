<?php


namespace App\Services\GitClients;

use App\Exceptions\GitClientOnlySyncException;
use App\Services\GitClient;

class AzureGitClient extends GitClient
{
    /**
     * @throws \Exception
     */
    public function __construct(string $remoteUrlOutput)
    {
        $regex = '/ssh.dev.azure.com:v3\/cegid[:\/](.*)\/(.*)/';
        if (!preg_match($regex, $remoteUrlOutput, $matches)) {
            throw new \Exception("Failed to extract azure username and repository from the remote URL: $remoteUrlOutput");
        }

        parent::__construct($matches[1], $matches[2]);
    }

    /**
     * @throws GitClientOnlySyncException
     */
    public function prNumberFromMergeCommit(string $mergeCommit): string
    {
        $commitDescription = exec("git show --no-patch --pretty=format:%s $mergeCommit");
        preg_match('/PR [0-9]+/', $commitDescription, $matches);
        if (count($matches) === 0) {
            throw new GitClientOnlySyncException($commitDescription);
        }
        return ltrim($matches[0], 'PR ');
    }
}
