<?php


namespace App\Services\RepositoryClients;

use App\Exceptions\RepositoryException;
use App\Services\Repository;

class AzureRepository extends Repository
{
    public function prLink(string $prNumber): string
    {
        return "https://dev.azure.com/cegid/{$this->gitClient->username}/_git/{$this->gitClient->repository}/pullrequest/{$prNumber}";
    }

    /**
     * @throws RepositoryException
     */
    public function getPRInfo(string $prNumber): array
    {
        throw new RepositoryException('API NOT INTEGRATED YET');
    }
}
