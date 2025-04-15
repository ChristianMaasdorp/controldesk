<?php

namespace App\Services;
use Github\Client;

class GithubService{
    public static function syncGithub($user){

        $client = new Client();
        $client->authenticate(config('github.token'), null, Client::AUTH_ACCESS_TOKEN);
// Get list of branches
        $branches = $client->repo()->branches('owner', 'repo');

// Get commits for a branch
        $commits = $client->repo()->commits()->all('owner', 'repo', ['sha' => 'branch-name']);

// Get a single commit
        dd($branches,$commits);
    }
}
