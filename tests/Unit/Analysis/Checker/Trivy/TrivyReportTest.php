<?php

namespace App\Tests\Unit\Analysis\Checker\Trivy;

use MBO\GitManager\Analysis\Checker\Trivy\TrivyReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrivyReportTest extends TestCase
{
    /**
     * Build a trivy report content with the given results.
     *
     * @param array<int,array<string,mixed>> $results
     */
    private function createTrivyContent(array $results): string
    {
        $content = json_encode([
            'SchemaVersion' => 2,
            'ArtifactType' => 'filesystem',
            'Results' => $results,
        ]);
        $this->assertIsString($content);

        return $content;
    }

    /**
     * @param array<int,array<string,mixed>> $vulnerabilities
     *
     * @return array<string,mixed>
     */
    private function createResult(string $target, array $vulnerabilities): array
    {
        return [
            'Target' => $target,
            'Class' => 'lang-pkgs',
            'Type' => 'composer',
            'Vulnerabilities' => $vulnerabilities,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function createVulnerability(string $id, string $severity): array
    {
        return [
            'VulnerabilityID' => $id,
            'PkgName' => 'symfony/http-kernel',
            'InstalledVersion' => '6.4.0',
            'FixedVersion' => '6.4.1',
            'Severity' => $severity,
            'Title' => 'a vulnerability',
        ];
    }

    /**
     * @return array<string,array{0:string|null}>
     */
    public static function provideEmptyContent(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'blank string' => ["  \n"],
            'invalid json' => ['{oops'],
            'json scalar' => ['42'],
            'no results' => ['{"SchemaVersion":2}'],
            'empty results' => ['{"Results":[]}'],
            'results not an array' => ['{"Results":"oops"}'],
            'no vulnerabilities' => ['{"Results":[{"Target":"composer.lock"}]}'],
            'vulnerabilities not an array' => ['{"Results":[{"Vulnerabilities":"oops"}]}'],
        ];
    }

    #[DataProvider('provideEmptyContent')]
    public function testFromInvalidOrEmptyContent(?string $content): void
    {
        $report = TrivyReport::fromJson($content);

        $this->assertSame([], $report->getVulnerabilities());
        $this->assertSame([], $report->getSeverityById());
        $this->assertSame([], $report->countBySeverity());
    }

    public function testGetSeverityById(): void
    {
        $report = TrivyReport::fromJson($this->createTrivyContent([
            $this->createResult('composer.lock', [
                $this->createVulnerability('CVE-2024-0001', 'HIGH'),
                $this->createVulnerability('CVE-2024-0002', 'CRITICAL'),
            ]),
        ]));

        $this->assertSame([
            'CVE-2024-0002' => 'CRITICAL',
            'CVE-2024-0001' => 'HIGH',
        ], $report->getSeverityById());
    }

    public function testCountBySeverity(): void
    {
        $report = TrivyReport::fromJson($this->createTrivyContent([
            $this->createResult('composer.lock', [
                $this->createVulnerability('CVE-2024-0001', 'HIGH'),
                $this->createVulnerability('CVE-2024-0002', 'CRITICAL'),
                $this->createVulnerability('CVE-2024-0003', 'HIGH'),
            ]),
        ]));

        $this->assertSame([
            'CRITICAL' => 1,
            'HIGH' => 2,
        ], $report->countBySeverity());
    }

    /**
     * The vulnerabilities are displayed from the most to the least critical one,
     * keeping the order of the report for a given severity.
     */
    public function testGetVulnerabilitiesIsSortedBySeverity(): void
    {
        $report = TrivyReport::fromJson($this->createTrivyContent([
            $this->createResult('composer.lock', [
                $this->createVulnerability('CVE-2024-0001', 'HIGH'),
                $this->createVulnerability('CVE-2024-0002', 'MEDIUM'),
                $this->createVulnerability('CVE-2024-0003', 'CRITICAL'),
                $this->createVulnerability('CVE-2024-0004', 'HIGH'),
                $this->createVulnerability('CVE-2024-0005', 'CRITICAL'),
            ]),
            $this->createResult('package-lock.json', [
                ['VulnerabilityID' => 'CVE-2024-0006'],
            ]),
        ]));

        $this->assertSame(
            [
                'CVE-2024-0003',
                'CVE-2024-0005',
                'CVE-2024-0001',
                'CVE-2024-0004',
                'CVE-2024-0002',
                'CVE-2024-0006',
            ],
            array_column($report->getVulnerabilities(), 'VulnerabilityID')
        );
    }

    /**
     * A vulnerability affecting several targets is listed twice but counted once.
     */
    public function testVulnerabilityInSeveralTargets(): void
    {
        $report = TrivyReport::fromJson($this->createTrivyContent([
            $this->createResult('composer.lock', [
                $this->createVulnerability('CVE-2024-0001', 'HIGH'),
            ]),
            $this->createResult('demo/composer.lock', [
                $this->createVulnerability('CVE-2024-0001', 'HIGH'),
            ]),
        ]));

        $this->assertCount(2, $report->getVulnerabilities());
        $this->assertSame(['CVE-2024-0001' => 'HIGH'], $report->getSeverityById());
        $this->assertSame(['HIGH' => 1], $report->countBySeverity());
    }

    public function testGetVulnerabilitiesReportsTheTarget(): void
    {
        $report = TrivyReport::fromJson($this->createTrivyContent([
            $this->createResult('composer.lock', [
                $this->createVulnerability('CVE-2024-0001', 'HIGH'),
            ]),
        ]));

        $vulnerabilities = $report->getVulnerabilities();

        $this->assertCount(1, $vulnerabilities);
        $this->assertSame('composer.lock', $vulnerabilities[0]['Target']);
        $this->assertSame('CVE-2024-0001', $vulnerabilities[0]['VulnerabilityID']);
    }

    public function testGetVulnerabilitiesStripPrefix(): void
    {
        $report = TrivyReport::fromJson($this->createTrivyContent([
            $this->createResult('/data/github.com/mborne/demo/composer.lock', [
                $this->createVulnerability('CVE-2024-0001', 'HIGH'),
            ]),
        ]));

        $vulnerabilities = $report->getVulnerabilities('/data/github.com/mborne/demo/');

        $this->assertSame('composer.lock', $vulnerabilities[0]['Target']);
    }

    public function testGetVulnerabilitiesKeepsUnrelatedTarget(): void
    {
        $report = TrivyReport::fromJson($this->createTrivyContent([
            $this->createResult('/elsewhere/composer.lock', [
                $this->createVulnerability('CVE-2024-0001', 'HIGH'),
            ]),
        ]));

        $vulnerabilities = $report->getVulnerabilities('/data/github.com/mborne/demo/');

        $this->assertSame('/elsewhere/composer.lock', $vulnerabilities[0]['Target']);
    }

    public function testResultWithoutTarget(): void
    {
        $report = TrivyReport::fromJson($this->createTrivyContent([
            ['Vulnerabilities' => [$this->createVulnerability('CVE-2024-0001', 'HIGH')]],
        ]));

        $vulnerabilities = $report->getVulnerabilities('/data/github.com/mborne/demo/');

        $this->assertArrayNotHasKey('Target', $vulnerabilities[0]);
    }

    public function testVulnerabilityWithoutIdOrSeverity(): void
    {
        $report = TrivyReport::fromJson($this->createTrivyContent([
            $this->createResult('composer.lock', [
                ['Title' => 'a vulnerability'],
                ['VulnerabilityID' => ['unexpected' => 'type'], 'Severity' => ['unexpected' => 'type']],
            ]),
        ]));

        $this->assertSame(['unknown' => 'UNKNOWN'], $report->getSeverityById());
        $this->assertSame(['UNKNOWN' => 1], $report->countBySeverity());
    }
}
