<?php

namespace App\Tests\Process;

use MBO\GitManager\Process\SymfonyProcessRunner;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

final class SymfonyProcessRunnerTest extends TestCase
{
    /**
     * The debug messages reported by the runner, with their context interpolated.
     *
     * @var string[]
     */
    private array $debugMessages = [];

    protected function setUp(): void
    {
        $this->debugMessages = [];
    }

    /**
     * Logger collecting the debug messages.
     */
    private function createLogger(): LoggerInterface
    {
        $collect = function (string $message): void {
            $this->debugMessages[] = $message;
        };

        return new class($collect) extends AbstractLogger {
            public function __construct(private \Closure $collect)
            {
            }

            /**
             * @param mixed[] $context
             */
            public function log($level, \Stringable|string $message, array $context = []): void
            {
                if ('debug' !== $level) {
                    return;
                }
                $message = (string) $message;
                foreach ($context as $key => $value) {
                    $message = str_replace(
                        '{'.$key.'}',
                        is_scalar($value) ? (string) $value : '?',
                        $message
                    );
                }
                ($this->collect)($message);
            }
        };
    }

    public function testRunSuccessfulCommand(): void
    {
        $runner = new SymfonyProcessRunner($this->createLogger());

        $result = $runner->run(['echo', 'hello']);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame("hello\n", $result->output);

        $this->assertCount(2, $this->debugMessages);
        $this->assertSame('[echo] $ echo hello', $this->debugMessages[0]);
        $this->assertMatchesRegularExpression('#^\[echo\] exitCode=0 \([0-9.]+s\)$#', $this->debugMessages[1]);
    }

    public function testRunFailingCommandLogsTheErrorOutput(): void
    {
        $runner = new SymfonyProcessRunner($this->createLogger());

        $result = $runner->run(['php', '-r', 'fwrite(STDERR, "boom"); exit(3);']);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(3, $result->exitCode);

        $this->assertCount(3, $this->debugMessages);
        $this->assertStringContainsString('[php] exitCode=3', $this->debugMessages[1]);
        $this->assertSame('[php] boom', $this->debugMessages[2]);
    }

    public function testRunUsesTheGivenWorkingDirectory(): void
    {
        $runner = new SymfonyProcessRunner($this->createLogger());

        $result = $runner->run(['pwd'], sys_get_temp_dir());

        $this->assertSame(sys_get_temp_dir(), trim($result->output));
        $this->assertSame('[pwd] $ cd '.sys_get_temp_dir().' && pwd', $this->debugMessages[0]);
    }
}
