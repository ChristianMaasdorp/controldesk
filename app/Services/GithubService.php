<?php

namespace App\Services;
use Github\Client;
use App\Models\Project;

class GithubService{
    public static function syncGithub($user){
        // This method is not used, can be removed if needed
    }

    public static function getCommitsForBranch(string $branchName, Project $project): array
    {
        if (empty($project->github_repository_url) || empty($project->github_api_key)) {
            throw new \Exception('GitHub repository URL or API key not configured for this project');
        }

        $client = new Client();
        $client->authenticate($project->github_api_key, null, Client::AUTH_ACCESS_TOKEN);

        // Extract owner and repo from the repository URL
        $repoUrl = $project->github_repository_url;
        $parts = parse_url($repoUrl);
        $path = trim($parts['path'], '/');
        [$owner, $repo] = explode('/', $path);

        $commits = $client->repo()->commits()->all($owner, $repo, ['sha' => $branchName]);

        $commitDetails = [];

        foreach ($commits as $commit) {
            $commitData = $client->repo()->commits()->show($owner, $repo, $commit['sha']);

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
