<?php


namespace App\Services\RepositoryClients;

use App\Exceptions\RepositoryException;
use App\Services\GitClient;
use App\Services\Repository;
use Illuminate\Support\Facades\Http;

class BitbucketRepository extends Repository
{
    public ?string $bearer;

    public function __construct(GitClient $gitClient)
    {
        $this->bearer = env('BITBUCKET_API_TOKEN');
        parent::__construct($gitClient);
    }

    public function prLink(string $prNumber): string
    {
        return "https://bitbucket.org/{$this->gitClient->username}/{$this->gitClient->repository}/pull-requests/{$prNumber}";
    }

    /**
     * @throws RepositoryException
     */
    public function getPRInfo(string $prNumber): array
    {
        $response = Http::withHeaders(['Authorization' => "Bearer {$this->bearer}"])
            ->get("https://api.bitbucket.org/2.0/repositories/{$this->gitClient->username}/{$this->gitClient->repository}/pullrequests/{$prNumber}")
            ->json();

        if (isset($response['error'])) {
            throw new RepositoryException($response['error']['message']);
        }

        $link = preg_match('/(https:\/\/linear\.app\/revo\/issue\/REV-[0-9]+)/', $response['description'], $matches) ? $matches[1] : null;
        return [$link, $response['title'], $response['description']];
    }
}
