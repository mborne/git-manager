<?php

namespace App\Tests\Export;

use MBO\GitManager\Analysis\Checker\GitleaksChecker;
use MBO\GitManager\Analysis\Checker\TrivyChecker;
use MBO\GitManager\Entity\Project;
use MBO\GitManager\Export\CsvExporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsvExporterTest extends TestCase
{
    private const HEADER = 'NAME,DESCRIPTION,VISIBILITY,ARCHIVED,README,LICENSE,SIZE_MO,LAST_ACTIVITY,TRIVY_CRITICAL,TRIVY_HIGH,TRIVY_LOW,GITLEAKS_COUNT';

    /**
     * @param array<string,mixed> $checks
     * @param array<string,mixed> $metadata
     */
    private function createProject(
        string $fullName = 'github.com/mborne/demo',
        ?string $description = null,
        ?string $visibility = 'public',
        bool $archived = false,
        array $checks = [],
        array $metadata = [],
    ): Project {
        $project = new Project();
        $project->setFullName($fullName);
        $project->setDescription($description);
        $project->setVisibility($visibility);
        $project->setArchived($archived);
        $project->setChecks($checks);
        $project->setMetadata($metadata);

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
     * Get the data lines (header and trailing newline removed).
     *
     * @return array<int,string>
     */
    private function getDataLines(string $content): array
    {
        $lines = explode("\n", rtrim($content, "\n"));
        array_shift($lines);

        return $lines;
    }

    /**
     * Get the columns of the single project exported.
     *
     * @param array<string,mixed> $checks
     * @param array<string,mixed> $metadata
     *
     * @return array<int,string>
     */
    private function exportColumns(
        ?string $description = null,
        ?string $visibility = 'public',
        bool $archived = false,
        array $checks = [],
        array $metadata = [],
    ): array {
        $project = $this->createProject(
            description: $description,
            visibility: $visibility,
            archived: $archived,
            checks: $checks,
            metadata: $metadata
        );
        $content = (new CsvExporter())->exportProjects([$project]);

        $lines = $this->getDataLines($content);
        $this->assertCount(1, $lines);

        return str_getcsv($lines[0]);
    }

    public function testExportWithoutProject(): void
    {
        $content = (new CsvExporter())->exportProjects([]);

        $this->assertSame(self::HEADER."\n", $content);
    }

    public function testExportProjects(): void
    {
        $projects = [
            $this->createProject(
                'github.com/mborne/demo',
                description: 'A demo project',
                checks: array_merge(
                    ['readme' => true, 'license' => 'LICENSE.md'],
                    $this->createChecks(['HIGH' => 2, 'CRITICAL' => 1, 'MEDIUM' => 3], 4)
                ),
                metadata: ['size' => 2621440, 'last_activity' => '2024-07-15T14:48:36+00:00']
            ),
            $this->createProject(
                'github.com/mborne/legacy',
                visibility: 'private',
                archived: true
            ),
        ];

        $expected = <<<'CSV'
            NAME,DESCRIPTION,VISIBILITY,ARCHIVED,README,LICENSE,SIZE_MO,LAST_ACTIVITY,TRIVY_CRITICAL,TRIVY_HIGH,TRIVY_LOW,GITLEAKS_COUNT
            github.com/mborne/demo,"A demo project",public,0,1,LICENSE.md,2.5,2024-07-15,1,2,0,4
            github.com/mborne/legacy,,private,1,0,0,0.0,0000-00-00,0,0,0,0

            CSV;

        $this->assertSame($expected, (new CsvExporter())->exportProjects($projects));
    }

    public function testExportProjectsFromATraversable(): void
    {
        $projects = (function (): \Generator {
            yield $this->createProject('github.com/mborne/demo');
            yield $this->createProject('github.com/mborne/legacy');
        })();

        $this->assertSame([
            'github.com/mborne/demo,,public,0,0,0,0.0,0000-00-00,0,0,0,0',
            'github.com/mborne/legacy,,public,0,0,0,0.0,0000-00-00,0,0,0,0',
        ], $this->getDataLines((new CsvExporter())->exportProjects($projects)));
    }

    public function testProjectWithoutDescription(): void
    {
        $columns = $this->exportColumns(description: null);

        $this->assertSame('', $columns[1]);
    }

    public function testProjectWithoutVisibility(): void
    {
        $columns = $this->exportColumns(visibility: null);

        $this->assertSame('unknown', $columns[2]);
    }

    /**
     * @return array<string,array{0:bool,1:string}>
     */
    public static function provideArchived(): array
    {
        return [
            'archived' => [true, '1'],
            'not archived' => [false, '0'],
        ];
    }

    #[DataProvider('provideArchived')]
    public function testArchivedIsExportedAsABoolean(bool $archived, string $expected): void
    {
        $columns = $this->exportColumns(archived: $archived);

        $this->assertSame($expected, $columns[3]);
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:string}>
     */
    public static function provideReadmeChecks(): array
    {
        return [
            'readme found' => [['readme' => true], '1'],
            'readme not found' => [['readme' => false], '0'],
            'checker not run' => [[], '0'],
        ];
    }

    /**
     * @param array<string,mixed> $checks
     */
    #[DataProvider('provideReadmeChecks')]
    public function testReadmeCheck(array $checks, string $expected): void
    {
        $columns = $this->exportColumns(checks: $checks);

        $this->assertSame($expected, $columns[4]);
    }

    /**
     * The license column reports the filename found by the checker.
     *
     * @return array<string,array{0:array<string,mixed>,1:string}>
     */
    public static function provideLicenseChecks(): array
    {
        return [
            'license found' => [['license' => 'LICENSE'], 'LICENSE'],
            'licence found' => [['license' => 'LICENCE.txt'], 'LICENCE.txt'],
            'license not found' => [['license' => false], '0'],
            'checker not run' => [[], '0'],
        ];
    }

    /**
     * @param array<string,mixed> $checks
     */
    #[DataProvider('provideLicenseChecks')]
    public function testLicenseCheck(array $checks, string $expected): void
    {
        $columns = $this->exportColumns(checks: $checks);

        $this->assertSame($expected, $columns[5]);
    }

    /**
     * The size is stored in bytes and exported in megabytes.
     *
     * @return array<string,array{0:array<string,mixed>,1:string}>
     */
    public static function provideMetadataSize(): array
    {
        return [
            'one megabyte' => [['size' => 1024 * 1024], '1.0'],
            'rounded to one decimal' => [['size' => 2621440 + 1024 * 60], '2.6'],
            'less than a megabyte' => [['size' => 1024], '0.0'],
            'size as a string' => [['size' => '1048576'], '1.0'],
            'size is not numeric' => [['size' => 'oops'], '0.0'],
            'no metadata' => [[], '0.0'],
        ];
    }

    /**
     * @param array<string,mixed> $metadata
     */
    #[DataProvider('provideMetadataSize')]
    public function testSizeIsExportedInMegabytes(array $metadata, string $expected): void
    {
        $columns = $this->exportColumns(metadata: $metadata);

        $this->assertSame($expected, $columns[6]);
    }

    /**
     * The last activity is collected as an RFC3339 date and exported as a day.
     *
     * @return array<string,array{0:array<string,mixed>,1:string}>
     */
    public static function provideMetadataActivity(): array
    {
        return [
            'last activity' => [['last_activity' => '2024-07-15T14:48:36+00:00'], '2024-07-15'],
            'repository without commit' => [['last_activity' => null], '0000-00-00'],
            'no metadata' => [[], '0000-00-00'],
        ];
    }

    /**
     * @param array<string,mixed> $metadata
     */
    #[DataProvider('provideMetadataActivity')]
    public function testLastActivity(array $metadata, string $expected): void
    {
        $columns = $this->exportColumns(metadata: $metadata);

        $this->assertSame($expected, $columns[7]);
    }

    public function testScanCounts(): void
    {
        $columns = $this->exportColumns(
            checks: $this->createChecks(['HIGH' => 2, 'CRITICAL' => 1, 'MEDIUM' => 3], 4)
        );

        $this->assertSame(['1', '2', '0', '4'], array_slice($columns, 8));
    }

    /**
     * The LOW severity is exported when it is reported by the scan.
     */
    public function testLowSeverityCount(): void
    {
        $columns = $this->exportColumns(checks: $this->createChecks(['LOW' => 7], 0));

        $this->assertSame(['0', '0', '7', '0'], array_slice($columns, 8));
    }

    /**
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function provideChecksWithoutSummary(): array
    {
        return [
            'no check' => [[]],
            'checks of another checker' => [['readme' => true]],
            'scan disabled' => [[TrivyChecker::NAME => null, GitleaksChecker::NAME => null]],
            'failed scan' => [[TrivyChecker::NAME => ['success' => false, 'summary' => false], GitleaksChecker::NAME => ['success' => false, 'summary' => false]]],
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
        $columns = $this->exportColumns(checks: $checks);

        $this->assertSame(['0', '0', '0', '0'], array_slice($columns, 8));
    }

    /**
     * The counters are exported as integers whatever the type stored in the checks.
     */
    public function testCountsAreExportedAsIntegers(): void
    {
        $columns = $this->exportColumns(checks: $this->createChecks(['CRITICAL' => '2', 'HIGH' => 1.9], 3));

        $this->assertSame(['2', '1', '0', '3'], array_slice($columns, 8));
    }

    /**
     * The special characters must be escaped to keep one project per line.
     */
    public function testDescriptionIsEscaped(): void
    {
        $description = "A \"quoted\", multiline\ndescription";
        $content = (new CsvExporter())->exportProjects([$this->createProject(description: $description)]);

        $this->assertStringContainsString("\"A \"\"quoted\"\", multiline\ndescription\"", $content);

        $lines = $this->getDataLines($content);
        $this->assertCount(2, $lines, 'the newline is kept inside the quoted field');
        $this->assertSame($description, str_getcsv(implode("\n", $lines))[1]);
    }
}
