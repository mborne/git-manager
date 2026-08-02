<?php

namespace App\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use MBO\GitManager\Analysis\Checker\GitleaksChecker;
use MBO\GitManager\Analysis\Checker\TrivyChecker;
use MBO\GitManager\Entity\Project;
use MBO\GitManager\Storage\GitRepositoryStore;
use MBO\GitManager\Storage\ReportStoreInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class ProjectControllerTest extends WebTestCase
{
    private const PROJECT_ID = '00000000-0000-3000-8000-000000000001';

    public function testIndex(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#projects');
    }

    /**
     * Ensures that the details of a project are rendered with the reports.
     */
    public function testDetails(): void
    {
        $client = self::createClient();
        $this->createProject($client);

        $client->request('GET', '/'.self::PROJECT_ID);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'github.com/mborne/sample');

        $content = (string) $client->getResponse()->getContent();
        // the last activity is displayed as a day (the fetch date being 2026-01-01)
        $this->assertStringContainsString('2025-12-24', $content);
        // the secret and the vulnerability are reported with their location relative to the repository
        $this->assertStringContainsString('config/settings.yaml', $content);
        $this->assertStringNotContainsString($this->getRepositoryPath(), $content);
        $this->assertStringContainsString('composer.lock', $content);
        $this->assertStringContainsString('CVE-2024-0001', $content);
    }

    public function testDetailsNotFound(): void
    {
        $client = self::createClient();
        $client->request('GET', '/00000000-0000-3000-8000-00000000ffff');

        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Get the local path of the git repository (used as prefix in the reports).
     */
    private function getRepositoryPath(): string
    {
        /** @var GitRepositoryStore $gitRepositoryStore */
        $gitRepositoryStore = static::getContainer()->get(GitRepositoryStore::class);

        return $gitRepositoryStore->getPath('github.com/mborne/sample');
    }

    /**
     * Create a project with a gitleaks and a trivy report.
     */
    private function createProject(KernelBrowser $client): void
    {
        $container = $client->getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine')->getManager();

        $id = Uuid::fromString(self::PROJECT_ID);
        $project = $entityManager->find(Project::class, $id) ?? new Project();
        $project
            ->setId($id)
            ->setName('sample')
            ->setFullName('github.com/mborne/sample')
            ->setHttpUrl('https://github.com/mborne/sample')
            ->setDescription('A sample project')
            ->setDefaultBranch('master')
            ->setArchived(false)
            ->setVisibility('public')
            ->setFetchedAt(new \DateTime('2026-01-01 10:00:00'))
            ->setMetadata([
                'size' => 1048576,
                'tags' => ['count' => 1, 'latest' => 'v1.0.0'],
                'last_activity' => '2025-12-24T09:32:11+00:00',
            ])
            ->setChecks([
                'readme' => true,
                'license' => 'MIT',
                'gitleaks' => ['success' => true, 'summary' => ['count' => 1]],
                'trivy' => ['success' => true, 'summary' => ['CRITICAL' => 1, 'HIGH' => 0]],
            ])
        ;
        $entityManager->persist($project);
        $entityManager->flush();

        /** @var ReportStoreInterface $reportStore */
        $reportStore = $container->get(ReportStoreInterface::class);
        $reportStore->write(GitleaksChecker::NAME, $id, (string) json_encode([
            'runs' => [[
                'results' => [[
                    'ruleId' => 'generic-api-key',
                    'locations' => [[
                        'physicalLocation' => [
                            'artifactLocation' => ['uri' => $this->getRepositoryPath().DIRECTORY_SEPARATOR.'config/settings.yaml'],
                            'region' => [
                                'startLine' => 12,
                                'snippet' => ['text' => 'api_key: s3cr3t'],
                            ],
                        ],
                    ]],
                    'partialFingerprints' => ['commitSha' => '0123456789abcdef'],
                ]],
            ]],
        ]));
        $reportStore->write(TrivyChecker::NAME, $id, (string) json_encode([
            'Results' => [[
                'Target' => 'composer.lock',
                'Vulnerabilities' => [[
                    'VulnerabilityID' => 'CVE-2024-0001',
                    'PrimaryURL' => 'https://example.org/CVE-2024-0001',
                    'PkgName' => 'acme/sample',
                    'InstalledVersion' => '1.0.0',
                    'FixedVersion' => '1.0.1',
                    'Severity' => 'CRITICAL',
                    'Title' => 'A sample vulnerability',
                ]],
            ]],
        ]));
    }
}
