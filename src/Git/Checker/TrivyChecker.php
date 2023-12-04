<?php

namespace MBO\GitManager\Git\Checker;

use Exception;
use Gitonomy\Git\Repository as GitRepository;
use MBO\GitManager\Git\CheckerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Perform a scan with trivy
 */
class TrivyChecker implements CheckerInterface
{
    public const SEVERITIES = ['HIGH','CRITICAL'];

    public function getName(): string
    {
        return 'trivy';
    }

    public function check(GitRepository $gitRepository): mixed
    {
        $workingDir = $gitRepository->getWorkingDir();
        $trivyReportPath = $workingDir.'/.trivy.json';
        $process = new Process([
            'trivy',
            'fs',
            '--scanners', 'vuln',
            '--severity', implode(',', self::SEVERITIES),
            '--format', 'json',
            '--output', $trivyReportPath,
            $workingDir
        ]);
        $process->setTimeout(1200);
        $process->run();

        $result = [
            'success' => $process->isSuccessful(),
            'vulnerabilities' => false,
            'summary' => false
        ];
        if (!$process->isSuccessful()) {
            echo $process->getErrorOutput();
        }
        if (!file_exists($trivyReportPath)) {
            return $result;
        }

        $report = json_decode(file_get_contents($trivyReportPath), true);
        $result['vulnerabilities'] = $this->getVulnerabilities($report);
        $result['summary'] = $this->getSummary($result['vulnerabilities']);
        return $result;
    }

    private function getVulnerabilities($report)
    {
        $vulnerabilities = [];
        if (isset($report['Results'])) {
            foreach ($report['Results'] as $reportResult) {
                if (!isset($reportResult['Vulnerabilities'])) {
                    continue;
                }
                foreach ($reportResult['Vulnerabilities'] as $vulnerability) {
                    $id       = $vulnerability['VulnerabilityID'];
                    $severity = $vulnerability['Severity'];

                    $vulnerabilities[$id] = $severity;
                }
            }
        }
        return $vulnerabilities;
    }

    public function getSummary(array $vulnerabilities)
    {
        foreach (self::SEVERITIES as $severity) {
            $stats[$severity] = 0;
        }
        foreach ($vulnerabilities as $id => $severity) {
            $stats[$severity]++;
        }
        return $stats;
    }

    /**
     * Test if trivy is available
     */
    public function isAvailable(): bool
    {
        try {
            $this->getVersion();
            return true;
        } catch(Exception $e) {
            return false;
        }
    }


    /**
     * Get trivy version
     */
    public function getVersion(): string
    {
        $process = new Process(['trivy', '--version']);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

}
