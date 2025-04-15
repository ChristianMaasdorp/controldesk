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


        foreach ($exports as $export){
            foreach($export['commits'] as $commit){
                dd($commit);
            }
        }

    }
}
