<?php


namespace App\Services;

use App\Exceptions\RepositoryException;

abstract class Repository
{

    public function __construct(
        protected GitClient $gitClient
    )
    {
    }

    public function preFetch(string $branch)
    {
        exec("git fetch origin $branch");
    }

    public function fetch(string $branch)
    {
        exec("git checkout $branch");
        exec("git merge origin/$branch");
    }

    abstract public function prLink(string $prNumber): string;

    /**
     * @throws RepositoryException
     */
    abstract public function getPRInfo(string $prNumber): array;
}
