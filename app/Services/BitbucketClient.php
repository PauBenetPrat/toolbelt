<?php


namespace App\Services;

use App\Exceptions\BitbucketClientException;
use Illuminate\Support\Facades\Http;

class BitbucketClient
{

    public ?string $bearer;

    public function __construct(protected GitClient $gitClient)
    {
        $this->bearer = env('BITBUCKET_API_TOKEN');
    }

    public function preFetch(string $branch)
    {
        exec("git fetch origin $branch");
    }

    public function prLink(string $prNumber): string
    {
        return "https://bitbucket.org/{$this->gitClient->username}/{$this->gitClient->repository}/pull-requests/{$prNumber}";
    }

    /**
     * @throws BitbucketClientException
     */
    public function getPRInfo(string $prNumber): array
    {
        $response = Http::withHeaders(['Authorization' => "Bearer {$this->bearer()}"])
            ->get("https://api.bitbucket.org/2.0/repositories/{$this->gitClient->username}/{$this->gitClient->repository}/pullrequests/{$prNumber}")
            ->json();

        throw_if(isset($response['error']), new BitbucketClientException($response['error']['message']));

        $link = preg_match('/(https:\/\/linear\.app\/revo\/issue\/REV-[0-9]+)/', $response['description'], $matches) ? $matches[1] : null;
        return [$link, $response['title']];

    }
}
