<?php

namespace App\Tests\Export;

use MBO\GitManager\Analysis\Checker\GitleaksChecker;
use MBO\GitManager\Analysis\Checker\Trivy\TrivyRunner;
use MBO\GitManager\Analysis\Checker\TrivyChecker;
use MBO\GitManager\Entity\Project;
use MBO\GitManager\Export\MetricsExporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MetricsExporterTest extends TestCase
{
    /**
     * @param array<string,mixed> $checks
     */
    private function createProject(
        string $fullName = 'github.com/mborne/demo',
        bool $archived = false,
        ?string $visibility = 'public',
        array $checks = [],
    ): Project {
        $project = new Project();
        $project->setFullName($fullName);
        $project->setArchived($archived);
        $project->setVisibility($visibility);
        $project->setChecks($checks);

        return $project;
    }

    /**
     * Build the checks of a project scanned by trivy and gitleaks.
     *
     * Note that the counts are typed as mixed as they are read back from a JSON column.
     *
     * @param array<string,mixed> $severityCounts
     *
     * @return array<string,mixed>
     */
    private function createChecks(array $severityCounts, int $secretCount): array
    {
        return [
            TrivyChecker::NAME => [
                'success' => true,
                'summary' => $severityCounts,
            ],
            GitleaksChecker::NAME => [
                'success' => true,
                'summary' => ['count' => $secretCount],
            ],
        ];
    }

    /**
     * Get the metric lines (comments and trailing newline removed).
     *
     * @return array<int,string>
     */
    private function getMetricLines(string $content): array
    {
        $lines = explode("\n", rtrim($content, "\n"));

        return array_values(array_filter($lines, fn (string $line): bool => !str_starts_with($line, '#')));
    }

    public function testExportWithoutProject(): void
    {
        $content = (new MetricsExporter())->exportProjects([]);

        $this->assertSame([], $this->getMetricLines($content));
        $this->assertStringContainsString("# TYPE gitmanager_trivy_total gauge\n", $content);
        $this->assertStringContainsString("# TYPE gitmanager_gitleaks_total gauge\n", $content);
    }

    /**
     * The exposition format requires a trailing newline.
     */
    public function testExportEndsWithANewline(): void
    {
        $content = (new MetricsExporter())->exportProjects([$this->createProject()]);

        $this->assertStringEndsWith("\n", $content);
        $this->assertStringNotContainsString("\n\n", $content);
    }

    public function testExportProjects(): void
    {
        $projects = [
            $this->createProject(
                'github.com/mborne/demo',
                checks: $this->createChecks(['HIGH' => 2, 'CRITICAL' => 1, 'MEDIUM' => 3], 4)
            ),
            $this->createProject('github.com/mborne/legacy', archived: true, visibility: 'private'),
        ];

        $expected = <<<'TXT'
            # HELP gitmanager_trivy_total Total number of vulnerabilities for a project
            # TYPE gitmanager_trivy_total gauge
            gitmanager_trivy_total{project="github.com/mborne/demo",archived="false",visibility="public"} 6
            gitmanager_trivy_total{project="github.com/mborne/legacy",archived="true",visibility="private"} 0
            # HELP gitmanager_trivy_high Number of high vulnerabilities for a project
            # TYPE gitmanager_trivy_high gauge
            gitmanager_trivy_high{project="github.com/mborne/demo",archived="false",visibility="public"} 2
            gitmanager_trivy_high{project="github.com/mborne/legacy",archived="true",visibility="private"} 0
            # HELP gitmanager_trivy_critical Number of critical vulnerabilities for a project
            # TYPE gitmanager_trivy_critical gauge
            gitmanager_trivy_critical{project="github.com/mborne/demo",archived="false",visibility="public"} 1
            gitmanager_trivy_critical{project="github.com/mborne/legacy",archived="true",visibility="private"} 0
            # HELP gitmanager_trivy_medium Number of medium vulnerabilities for a project
            # TYPE gitmanager_trivy_medium gauge
            gitmanager_trivy_medium{project="github.com/mborne/demo",archived="false",visibility="public"} 3
            gitmanager_trivy_medium{project="github.com/mborne/legacy",archived="true",visibility="private"} 0
            # HELP gitmanager_gitleaks_total Total number of secrets detected for a project
            # TYPE gitmanager_gitleaks_total gauge
            gitmanager_gitleaks_total{project="github.com/mborne/demo",archived="false",visibility="public"} 4
            gitmanager_gitleaks_total{project="github.com/mborne/legacy",archived="true",visibility="private"} 0

            TXT;

        $this->assertSame($expected, (new MetricsExporter())->exportProjects($projects));
    }

    /**
     * A metric is exposed for each severity reported by the scans.
     */
    public function testAMetricIsExportedForEachScannedSeverity(): void
    {
        $content = (new MetricsExporter())->exportProjects([$this->createProject()]);

        foreach (TrivyRunner::SEVERITIES as $severity) {
            $this->assertStringContainsString(
                sprintf("# TYPE gitmanager_trivy_%s gauge\n", strtolower($severity->value)),
                $content
            );
        }
    }

    /**
     * The projects are traversed once per metric, a traversable must not be exhausted.
     */
    public function testExportProjectsFromATraversable(): void
    {
        $projects = (function (): \Generator {
            yield $this->createProject('github.com/mborne/demo', checks: $this->createChecks(['HIGH' => 1], 2));
        })();

        $this->assertSame([
            'gitmanager_trivy_total{project="github.com/mborne/demo",archived="false",visibility="public"} 1',
            'gitmanager_trivy_high{project="github.com/mborne/demo",archived="false",visibility="public"} 1',
            'gitmanager_trivy_critical{project="github.com/mborne/demo",archived="false",visibility="public"} 0',
            'gitmanager_trivy_medium{project="github.com/mborne/demo",archived="false",visibility="public"} 0',
            'gitmanager_gitleaks_total{project="github.com/mborne/demo",archived="false",visibility="public"} 2',
        ], $this->getMetricLines((new MetricsExporter())->exportProjects($projects)));
    }

    /**
     * A severity which has no dedicated metric is still counted in the total.
     */
    public function testTotalIncludesUnexportedSeverities(): void
    {
        $project = $this->createProject(checks: $this->createChecks(['HIGH' => 1, 'LOW' => 4], 0));

        $lines = $this->getMetricLines((new MetricsExporter())->exportProjects([$project]));

        $this->assertStringEndsWith(' 5', $lines[0]);
        $this->assertStringNotContainsString('gitmanager_trivy_low', implode("\n", $lines));
    }

    /**
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function provideChecksWithoutSummary(): array
    {
        return [
            'no check' => [[]],
            'checks of another checker' => [['readme' => ['success' => true]]],
            'failed checks' => [[TrivyChecker::NAME => ['success' => false, 'summary' => false], GitleaksChecker::NAME => ['success' => false, 'summary' => false]]],
            'no summary' => [[TrivyChecker::NAME => ['success' => true], GitleaksChecker::NAME => ['success' => true]]],
            'summary is not an array' => [[TrivyChecker::NAME => ['summary' => 'oops'], GitleaksChecker::NAME => ['summary' => 'oops']]],
            'empty summary' => [[TrivyChecker::NAME => ['summary' => []], GitleaksChecker::NAME => ['summary' => []]]],
        ];
    }

    /**
     * @param array<string,mixed> $checks
     */
    #[DataProvider('provideChecksWithoutSummary')]
    public function testProjectWithoutSummaryReportsZero(array $checks): void
    {
        $content = (new MetricsExporter())->exportProjects([$this->createProject(checks: $checks)]);

        foreach ($this->getMetricLines($content) as $line) {
            $this->assertStringEndsWith(' 0', $line);
        }
    }

    /**
     * The counters are exposed as integers whatever the type stored in the checks.
     */
    public function testCountsAreExportedAsIntegers(): void
    {
        $project = $this->createProject(checks: $this->createChecks(['HIGH' => '2', 'CRITICAL' => 1.9], 3));

        $lines = $this->getMetricLines((new MetricsExporter())->exportProjects([$project]));

        $this->assertStringEndsWith(' 3', $lines[0]);
        $this->assertStringEndsWith(' 2', $lines[1]);
        $this->assertStringEndsWith(' 1', $lines[2]);
    }

    public function testProjectWithoutVisibility(): void
    {
        $project = $this->createProject(visibility: null);

        $lines = $this->getMetricLines((new MetricsExporter())->exportProjects([$project]));

        $this->assertStringContainsString('visibility="unknown"', $lines[0]);
    }

    /**
     * The label values are escaped according to the prometheus exposition format.
     */
    public function testLabelValuesAreEscaped(): void
    {
        $project = $this->createProject("github.com/a\"b\\c\nd", visibility: 'pu"blic');

        $lines = $this->getMetricLines((new MetricsExporter())->exportProjects([$project]));

        $this->assertCount(5, $lines);
        $this->assertStringStartsWith(
            'gitmanager_trivy_total{project="github.com/a\"b\\\\c\nd",archived="false",visibility="pu\"blic"}',
            $lines[0]
        );
    }
}
