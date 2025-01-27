<?php


namespace App\Services;

use App\Exceptions\GitClientOnlySyncException;

abstract class GitClient
{
    /**
     * @throws \Exception
     */
    public function __construct(
        readonly public string $username,
        readonly public string $repository
    )
    {
    }

    public function mergeCommitsBetween(string $mainBranch, string $releaseBranch): array
    {
        exec("git log {$mainBranch}..{$releaseBranch} --merges --pretty=format:\"%H\"", $output);
        return $output;
    }

    /**
     * @throws GitClientOnlySyncException
     */
    abstract public function prNumberFromMergeCommit(string $mergeCommit);
}
