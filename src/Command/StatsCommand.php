<?php

namespace MBO\GitManager\Command;

use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Clone remote git projects to local directory.
 *
 * @author mborne
 */
class StatsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('git:stats')
            ->setDescription('Compute stats on local repositories (DEPRECATED)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = $this->createLogger($output);

        $logger->warning('[git:stats] command is deprecated, no more need to run it to extract metadata and run checks');

        return self::SUCCESS;
    }

    /**
     * Create console logger.
     */
    protected function createLogger(OutputInterface $output): ConsoleLogger
    {
        $verbosityLevelMap = [
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
        ];

        return new ConsoleLogger($output, $verbosityLevelMap);
    }
}
