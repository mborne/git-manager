<?php

namespace MBO\GitManager\Process;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Run commands using symfony/process.
 */
final class SymfonyProcessRunner implements ProcessRunnerInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

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

        // Note that the raw command is reported as it is more readable than
        // the escaped command line, and that it is only reported once.
        $binary = $command[0] ?? '';
        $this->logger->debug(
            null === $workingDirectory
                ? '[{binary}] $ {command}'
                : '[{binary}] $ cd {workingDirectory} && {command}',
            [
                'binary' => $binary,
                'command' => implode(' ', $command),
                'workingDirectory' => $workingDirectory,
            ]
        );

        $startTime = microtime(true);
        $process->run();
        $duration = microtime(true) - $startTime;

        $result = new ProcessResult(
            $process->getExitCode() ?? 1,
            $process->getOutput(),
            $process->getErrorOutput()
        );
        $this->logger->debug('[{binary}] exitCode={exitCode} ({duration}s)', [
            'binary' => $binary,
            'exitCode' => $result->exitCode,
            'duration' => round($duration, 3),
        ]);
        if (!$result->isSuccessful()) {
            $this->logger->debug('[{binary}] {errorOutput}', [
                'binary' => $binary,
                'errorOutput' => trim($result->errorOutput),
            ]);
        }

        return $result;
    }
}
