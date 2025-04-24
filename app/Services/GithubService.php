<?php

namespace App\Services;
use Github\Client;
use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Github\Exception\RuntimeException;

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
        Log::info("Authenticated with GitHub API key");
        Log::info("Project ID: " . $project->id);
        Log::info("Project URL: " . $project->github_repository_url);
        Log::info("Branch Name: " . $branchName);
        Log::info("Project: " . $project);

        // Verify authentication
        // Extract owner and repo from the repository URL
        $repoUrl = $project->github_repository_url;
        $parts = parse_url($repoUrl);
        $path = trim($parts['path'], '/');
        [$owner, $repo] = explode('/', $path);
        $repositories = $client->repositories()->all();
        Log::info("Repositories: " . json_encode($repositories),JSON_PRETTY_PRINT);

        Log::info("Attempting to fetch commits for repository", [
            'owner' => $owner,
            'repo' => $repo,
            'branch' => $branchName,
            'project_id' => $project->id
        ]);

        try {
            // First verify if the repository exists and is accessible
            $client->repo()->show($owner, $repo);

            // Then try to get the commits
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
        } catch (RuntimeException $e) {
            $errorMessage = $e->getMessage();

            // Check for specific error types
            if (str_contains($errorMessage, 'Not Found')) {
                if (str_contains($errorMessage, 'Branch')) {
                    Log::error("Branch not found", [
                        'branch' => $branchName,
                        'owner' => $owner,
                        'repo' => $repo,
                        'project_id' => $project->id
                    ]);
                    throw new \Exception("Branch '{$branchName}' not found in repository");
                } else {
                    Log::error("Repository not found or not accessible", [
                        'owner' => $owner,
                        'repo' => $repo,
                        'project_id' => $project->id
                    ]);
                    throw new \Exception("Repository not found or not accessible. Please check the repository URL and API key.");
                }
            }

            // For other GitHub API errors
            Log::error("GitHub API error", [
                'error' => $errorMessage,
                'owner' => $owner,
                'repo' => $repo,
                'branch' => $branchName,
                'project_id' => $project->id
            ]);
            throw new \Exception("GitHub API error: " . $errorMessage);
        }
    }
}
