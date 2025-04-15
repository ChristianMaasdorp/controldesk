<?php

namespace App\Services;
use Github\Client;

class GithubService{
    public static function syncGithub($user){

        $client = new Client();
        $client->authenticate(config('github.token'), null, Client::AUTH_ACCESS_TOKEN);
// Get list of branches
        $branches = $client->repo()->branches('jacquestrdx123', 'CibaRebuildSystem');

        $exports=[];
// Get commits for a branch
        foreach($branches as $branch){
            $exports[$branch['name']]['commits'] = $client->repo()->commits()->all('jacquestrdx123', 'CibaRebuildSystem', ['sha' =>$branch['name']]);

        }

        foreach ($exports as $branch=> $export){
            foreach($export['commits'] as $index=> $exportCommit){
                $exportCommit = $client->repo()->commits()->show('jacquestrdx123', 'CibaRebuildSystem', $exportCommit['sha']);
                $exports[$branch]['commits'][$exportCommit['sha']]['commits'] = $exportCommit;
                echo json_encode($exportCommit);
            }
        }

    }

    public static function getCommitsForBranch(string $branchName): array
    {
        $client = new Client();
        $client->authenticate(config('github.token'), null, Client::AUTH_ACCESS_TOKEN);

        $username = 'jacquestrdx123';
        $repo = 'CibaRebuildSystem';

        $commits = $client->repo()->commits()->all($username, $repo, ['sha' => $branchName]);

        $commitDetails = [];

        foreach ($commits as $commit) {
            $commitData = $client->repo()->commits()->show($username, $repo, $commit['sha']);

            $commitDetails[] = [
                'sha' => $commitData['sha'],
                'message' => $commitData['commit']['message'],
                'author' => $commitData['commit']['author']['name'],
                'date' => $commitData['commit']['author']['date'],
                'files_changed' => $commitData['files'], // Contains filename, status, additions, deletions, patch
            ];
        }

        return $commitDetails;
    }
}
