<?php

namespace MBO\GitManager\Helpers;

use MBO\RemoteGit\ProjectInterface;
use Symfony\Component\Uid\Uuid;

class ProjectHelpers
{
    /**
     * Get UID V3 according to project URL.
     */
    public static function getUid(ProjectInterface $project): Uuid
    {
        $namespace = Uuid::fromString(Uuid::NAMESPACE_OID);

        return Uuid::v3($namespace, $project->getHttpUrl());
    }

    /**
     * The full name of a project (ex : "github.com/mborne/git-manager").
     */
    public static function getFullName(ProjectInterface $project): string
    {
        $host = parse_url($project->getHttpUrl(), PHP_URL_HOST);

        return $host.'/'.$project->getName();
    }
}
