<?php

namespace MBO\GitManager\Analysis\Checker\Trivy;

/**
 * JSON report produced by trivy.
 *
 * Note that this class performs no I/O so that the parsing can be tested in isolation.
 */
final readonly class TrivyReport
{
    /**
     * The severities from the most to the least critical one, the unlisted ones
     * being reported last.
     */
    private const SEVERITY_ORDER = ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW'];

    /**
     * @param array<int,array<string,mixed>> $vulnerabilities
     */
    private function __construct(
        private array $vulnerabilities,
    ) {
    }

    /**
     * Parse a trivy report, ignoring missing or invalid content.
     *
     * Note that the vulnerabilities are flattened, each one carrying the "Target"
     * of the result it belongs to, and sorted by decreasing severity.
     */
    public static function fromJson(?string $content): self
    {
        if (null === $content || '' === trim($content)) {
            return new self([]);
        }

        $report = json_decode($content, true);
        if (!is_array($report)) {
            return new self([]);
        }

        $results = $report['Results'] ?? null;
        if (!is_array($results)) {
            return new self([]);
        }

        $vulnerabilities = [];
        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }
            $target = isset($result['Target']) && is_scalar($result['Target'])
                ? (string) $result['Target']
                : null;
            foreach (self::getResultVulnerabilities($result) as $vulnerability) {
                if (null !== $target) {
                    $vulnerability['Target'] = $target;
                }
                $vulnerabilities[] = $vulnerability;
            }
        }

        return new self(self::sortBySeverity($vulnerabilities));
    }

    /**
     * Get the vulnerabilities sorted by decreasing severity, one entry per
     * affected target.
     *
     * @param string|null $stripPrefix prefix removed from the targets (ex : the repository path)
     *
     * @return array<int,array<string,mixed>>
     */
    public function getVulnerabilities(?string $stripPrefix = null): array
    {
        if (null === $stripPrefix || '' === $stripPrefix) {
            return $this->vulnerabilities;
        }

        return array_map(
            fn (array $vulnerability): array => self::stripTargetPrefix($vulnerability, $stripPrefix),
            $this->vulnerabilities
        );
    }

    /**
     * Get the severity of the vulnerabilities indexed by VulnerabilityID.
     *
     * Note that a vulnerability affecting several targets is reported once.
     *
     * @return array<string,string>
     */
    public function getSeverityById(): array
    {
        $severityById = [];
        foreach ($this->vulnerabilities as $vulnerability) {
            $severityById[self::getVulnerabilityId($vulnerability)] = self::getSeverity($vulnerability);
        }

        return $severityById;
    }

    /**
     * Get the number of distinct vulnerabilities per severity.
     *
     * @return array<string,int>
     */
    public function countBySeverity(): array
    {
        $counts = [];
        foreach ($this->getSeverityById() as $severity) {
            $counts[$severity] = ($counts[$severity] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Get the vulnerabilities of a given result.
     *
     * @param array<string,mixed> $result
     *
     * @return array<int,array<string,mixed>>
     */
    private static function getResultVulnerabilities(array $result): array
    {
        $vulnerabilities = $result['Vulnerabilities'] ?? null;
        if (!is_array($vulnerabilities)) {
            return [];
        }

        /** @var array<int,array<string,mixed>> $vulnerabilities */
        $vulnerabilities = array_values(array_filter($vulnerabilities, 'is_array'));

        return $vulnerabilities;
    }

    /**
     * Sort the vulnerabilities by decreasing severity, keeping the order of the
     * report for a given severity.
     *
     * @param array<int,array<string,mixed>> $vulnerabilities
     *
     * @return array<int,array<string,mixed>>
     */
    private static function sortBySeverity(array $vulnerabilities): array
    {
        usort(
            $vulnerabilities,
            fn (array $a, array $b): int => self::getSeverityRank($a) <=> self::getSeverityRank($b)
        );

        return $vulnerabilities;
    }

    /**
     * Get the rank of a vulnerability, the most critical ones coming first.
     *
     * @param array<string,mixed> $vulnerability
     */
    private static function getSeverityRank(array $vulnerability): int
    {
        $rank = array_search(self::getSeverity($vulnerability), self::SEVERITY_ORDER, true);

        return false === $rank ? count(self::SEVERITY_ORDER) : $rank;
    }

    /**
     * @param array<string,mixed> $vulnerability
     */
    private static function getVulnerabilityId(array $vulnerability): string
    {
        return isset($vulnerability['VulnerabilityID']) && is_scalar($vulnerability['VulnerabilityID'])
            ? (string) $vulnerability['VulnerabilityID']
            : 'unknown';
    }

    /**
     * @param array<string,mixed> $vulnerability
     */
    private static function getSeverity(array $vulnerability): string
    {
        return isset($vulnerability['Severity']) && is_scalar($vulnerability['Severity'])
            ? (string) $vulnerability['Severity']
            : 'UNKNOWN';
    }

    /**
     * Make the target of a vulnerability relative to a given prefix.
     *
     * @param array<string,mixed> $vulnerability
     *
     * @return array<string,mixed>
     */
    private static function stripTargetPrefix(array $vulnerability, string $prefix): array
    {
        $target = $vulnerability['Target'] ?? null;
        if (!is_string($target) || !str_starts_with($target, $prefix)) {
            return $vulnerability;
        }
        $vulnerability['Target'] = substr($target, strlen($prefix));

        return $vulnerability;
    }
}
