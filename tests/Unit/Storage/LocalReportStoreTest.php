<?php

namespace App\Tests\Unit\Storage;

use MBO\GitManager\Storage\LocalReportStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Symfony\Component\Uid\Uuid;

final class LocalReportStoreTest extends TestCase
{
    private const PROJECT_ID = '0b7b2b2e-1e2a-3d4f-8a9b-0c1d2e3f4a5b';
    private const OTHER_PROJECT_ID = '1c8c3c3f-2f3b-4e5a-9bac-1d2e3f4a5b6c';

    private string $dataDir;

    private LocalReportStore $reportStore;

    protected function setUp(): void
    {
        $this->dataDir = sys_get_temp_dir().'/git-manager-report-store-'.uniqid();
        $this->reportStore = new LocalReportStore($this->dataDir);
    }

    protected function tearDown(): void
    {
        (new SymfonyFilesystem())->remove($this->dataDir);
    }

    private function getProjectId(): Uuid
    {
        return Uuid::fromString(self::PROJECT_ID);
    }

    public function testWriteCreatesTheExpectedFile(): void
    {
        $this->reportStore->write('gitleaks', $this->getProjectId(), '{"runs":[]}');

        $this->assertFileExists($this->dataDir.'/gitleaks/'.self::PROJECT_ID.'.json');
    }

    public function testReadWrittenReport(): void
    {
        $this->reportStore->write('gitleaks', $this->getProjectId(), '{"runs":[]}');

        $this->assertSame('{"runs":[]}', $this->reportStore->read('gitleaks', $this->getProjectId()));
    }

    public function testWriteReplacesPreviousReport(): void
    {
        $this->reportStore->write('gitleaks', $this->getProjectId(), '{"runs":[]}');
        $this->reportStore->write('gitleaks', $this->getProjectId(), '{"runs":[1]}');

        $this->assertSame('{"runs":[1]}', $this->reportStore->read('gitleaks', $this->getProjectId()));
    }

    public function testReportsAreIsolatedByTool(): void
    {
        $this->reportStore->write('gitleaks', $this->getProjectId(), 'gitleaks-report');
        $this->reportStore->write('trivy', $this->getProjectId(), 'trivy-report');

        $this->assertSame('gitleaks-report', $this->reportStore->read('gitleaks', $this->getProjectId()));
        $this->assertSame('trivy-report', $this->reportStore->read('trivy', $this->getProjectId()));
    }

    public function testReadMissingReport(): void
    {
        $this->assertNull($this->reportStore->read('gitleaks', $this->getProjectId()));
    }

    public function testExists(): void
    {
        $this->assertFalse($this->reportStore->exists('gitleaks', $this->getProjectId()));

        $this->reportStore->write('gitleaks', $this->getProjectId(), '{"runs":[]}');

        $this->assertTrue($this->reportStore->exists('gitleaks', $this->getProjectId()));
    }

    public function testExistsIsScopedToTheTool(): void
    {
        $this->reportStore->write('gitleaks', $this->getProjectId(), '{"runs":[]}');

        $this->assertFalse($this->reportStore->exists('trivy', $this->getProjectId()));
    }

    public function testListUnknownTool(): void
    {
        $this->assertSame([], $this->reportStore->list('gitleaks'));
    }

    public function testListReportedProjects(): void
    {
        $this->reportStore->write('gitleaks', $this->getProjectId(), '{"runs":[]}');
        $this->reportStore->write('gitleaks', Uuid::fromString(self::OTHER_PROJECT_ID), '{"runs":[]}');
        $this->reportStore->write('trivy', $this->getProjectId(), '{"Results":[]}');

        $projectIds = array_map(
            fn (Uuid $projectId): string => (string) $projectId,
            $this->reportStore->list('gitleaks')
        );
        sort($projectIds);

        $this->assertSame([self::PROJECT_ID, self::OTHER_PROJECT_ID], $projectIds);
    }

    public function testListIgnoresUnexpectedFiles(): void
    {
        $this->reportStore->write('gitleaks', $this->getProjectId(), '{"runs":[]}');

        $symfonyFilesystem = new SymfonyFilesystem();
        $symfonyFilesystem->dumpFile($this->dataDir.'/gitleaks/not-a-uuid.json', 'ignored');
        $symfonyFilesystem->dumpFile($this->dataDir.'/gitleaks/'.self::OTHER_PROJECT_ID.'.txt', 'ignored');
        $symfonyFilesystem->mkdir($this->dataDir.'/gitleaks/subdirectory');

        $projectIds = $this->reportStore->list('gitleaks');

        $this->assertCount(1, $projectIds);
        $this->assertSame(self::PROJECT_ID, (string) $projectIds[0]);
    }
}
