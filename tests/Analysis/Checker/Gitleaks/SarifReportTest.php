<?php

namespace App\Tests\Analysis\Checker\Gitleaks;

use MBO\GitManager\Analysis\Checker\Gitleaks\SarifReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SarifReportTest extends TestCase
{
    /**
     * Build a SARIF report content with the given results.
     *
     * @param array<int,array<string,mixed>> $results
     */
    private function createSarifContent(array $results): string
    {
        $content = json_encode([
            'version' => '2.1.0',
            'runs' => [
                [
                    'tool' => ['driver' => ['name' => 'gitleaks']],
                    'results' => $results,
                ],
            ],
        ]);
        $this->assertIsString($content);

        return $content;
    }

    /**
     * @return array<string,mixed>
     */
    private function createFinding(string $ruleId, string $uri): array
    {
        return [
            'ruleId' => $ruleId,
            'message' => ['text' => 'secret detected'],
            'locations' => [
                [
                    'physicalLocation' => [
                        'artifactLocation' => ['uri' => $uri],
                        'region' => ['startLine' => 1],
                    ],
                ],
            ],
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
            'no runs' => ['{"version":"2.1.0"}'],
            'empty runs' => ['{"runs":[]}'],
            'no results' => ['{"runs":[{"tool":{}}]}'],
            'results not an array' => ['{"runs":[{"results":"oops"}]}'],
        ];
    }

    #[DataProvider('provideEmptyContent')]
    public function testFromInvalidOrEmptyContent(?string $content): void
    {
        $report = SarifReport::fromJson($content);

        $this->assertSame(0, $report->count());
        $this->assertSame([], $report->getFindings());
        $this->assertSame([], $report->countByRuleId());
    }

    public function testCountByRuleId(): void
    {
        $report = SarifReport::fromJson($this->createSarifContent([
            $this->createFinding('aws-access-token', '/data/github.com/mborne/demo/a.txt'),
            $this->createFinding('generic-api-key', '/data/github.com/mborne/demo/b.txt'),
            $this->createFinding('aws-access-token', '/data/github.com/mborne/demo/c.txt'),
        ]));

        $this->assertSame(3, $report->count());
        $this->assertSame(
            [
                'aws-access-token' => 2,
                'generic-api-key' => 1,
            ],
            $report->countByRuleId()
        );
    }

    public function testCountByRuleIdWithoutRuleId(): void
    {
        $report = SarifReport::fromJson($this->createSarifContent([
            ['message' => ['text' => 'secret detected']],
            ['ruleId' => ['unexpected' => 'type']],
        ]));

        $this->assertSame(['unknown' => 2], $report->countByRuleId());
    }

    public function testGetFindingsWithoutPrefix(): void
    {
        $finding = $this->createFinding('aws-access-token', '/data/github.com/mborne/demo/a.txt');
        $report = SarifReport::fromJson($this->createSarifContent([$finding]));

        $this->assertEquals([$finding], $report->getFindings());
    }

    public function testGetFindingsStripPrefix(): void
    {
        $report = SarifReport::fromJson($this->createSarifContent([
            $this->createFinding('aws-access-token', '/data/github.com/mborne/demo/config/secret.txt'),
        ]));

        $findings = $report->getFindings('/data/github.com/mborne/demo/');

        $this->assertSame(
            'config/secret.txt',
            $findings[0]['locations'][0]['physicalLocation']['artifactLocation']['uri']
        );
    }

    public function testGetFindingsKeepsUnrelatedUri(): void
    {
        $report = SarifReport::fromJson($this->createSarifContent([
            $this->createFinding('aws-access-token', '/elsewhere/secret.txt'),
        ]));

        $findings = $report->getFindings('/data/github.com/mborne/demo/');

        $this->assertSame(
            '/elsewhere/secret.txt',
            $findings[0]['locations'][0]['physicalLocation']['artifactLocation']['uri']
        );
    }

    public function testGetFindingsWithoutLocation(): void
    {
        $report = SarifReport::fromJson($this->createSarifContent([
            ['ruleId' => 'aws-access-token'],
        ]));

        $findings = $report->getFindings('/data/github.com/mborne/demo/');

        $this->assertSame([['ruleId' => 'aws-access-token']], $findings);
    }
}
