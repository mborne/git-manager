<?php

namespace MBO\GitManager\Storage;

use Symfony\Component\Uid\Uuid;

/**
 * Store the reports produced by the analysis tools (gitleaks, trivy,...),
 * one report per tool and per project.
 *
 * Note that the projects are identified by their id so that the storage
 * doesn't depend on the persistence of the projects.
 */
interface ReportStoreInterface
{
    /**
     * Get the ids of the projects having a report for a given tool.
     *
     * @return Uuid[]
     *
     * @throws ReportStoreException if the reports can't be listed
     */
    public function list(string $toolName): array;

    /**
     * Test if a report is available for a given tool and project.
     */
    public function exists(string $toolName, Uuid $projectId): bool;

    /**
     * Store the report of a project for a given tool, replacing any previous one.
     *
     * @throws ReportStoreException if the report can't be stored
     */
    public function write(string $toolName, Uuid $projectId, string $content): void;

    /**
     * Get the raw content of a report, null if it is missing or unreadable.
     *
     * Note that the parsing is left to the caller as it is tool specific.
     */
    public function read(string $toolName, Uuid $projectId): ?string;
}
