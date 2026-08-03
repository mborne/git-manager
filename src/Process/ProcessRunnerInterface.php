<?php

namespace MBO\GitManager\Process;

/**
 * Run external commands.
 *
 * Allows the replacement of the process execution in unit tests.
 */
interface ProcessRunnerInterface
{
    public const DEFAULT_TIMEOUT = 1200;

    /**
     * Run a command and wait for its completion.
     *
     * @param string[] $command
     */
    public function run(
        array $command,
        ?string $workingDirectory = null,
        int $timeout = self::DEFAULT_TIMEOUT,
    ): ProcessResult;
}
