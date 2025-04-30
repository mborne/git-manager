<?php

namespace MBO\GitManager\Helpers;

use MBO\RemoteGit\ProjectInterface;

class ProjectHelpers
{
    /**
     * Get the full name of a project (ex : "github.com/mborne/git-manager").
     */
    public function getFullName(ProjectInterface $project): string
    {
        $host = parse_url($project->getHttpUrl(), PHP_URL_HOST);

        return $host.'/'.$project->getName();
    }
}
