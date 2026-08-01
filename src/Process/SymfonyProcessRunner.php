<?php

namespace MBO\GitManager\Process;

use Symfony\Component\Process\Process;

/**
 * Run commands using symfony/process.
 */
final class SymfonyProcessRunner implements ProcessRunnerInterface
{
    public function run(
        array $command,
        ?string $workingDirectory = null,
        int $timeout = self::DEFAULT_TIMEOUT,
    ): ProcessResult {
        $process = new Process($command);
        if (null !== $workingDirectory) {
            $process->setWorkingDirectory($workingDirectory);
        }
        $process->setTimeout($timeout);
        $process->run();

        return new ProcessResult(
            $process->getExitCode() ?? 1,
            $process->getOutput(),
            $process->getErrorOutput()
        );
    }
}
