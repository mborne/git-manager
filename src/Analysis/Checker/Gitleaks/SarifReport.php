<?php

namespace MBO\GitManager\Analysis\Checker\Gitleaks;

/**
 * SARIF report produced by gitleaks.
 *
 * Note that this class performs no I/O so that the parsing can be tested in isolation.
 */
final readonly class SarifReport
{
    /**
     * @param array<int,array<string,mixed>> $findings
     */
    private function __construct(
        private array $findings,
    ) {
    }

    /**
     * Parse a SARIF report, ignoring missing or invalid content.
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

        $runs = $report['runs'] ?? null;
        if (!is_array($runs) || !isset($runs[0]) || !is_array($runs[0])) {
            return new self([]);
        }

        $results = $runs[0]['results'] ?? null;
        if (!is_array($results)) {
            return new self([]);
        }

        /** @var array<int,array<string,mixed>> $findings */
        $findings = array_values(array_filter($results, 'is_array'));

        return new self($findings);
    }

    /**
     * Get findings.
     *
     * @param string|null $stripPrefix prefix removed from the location URIs (ex : the repository path)
     *
     * @return array<int,array<string,mixed>>
     */
    public function getFindings(?string $stripPrefix = null): array
    {
        if (null === $stripPrefix || '' === $stripPrefix) {
            return $this->findings;
        }

        return array_map(
            fn (array $finding): array => self::stripLocationPrefix($finding, $stripPrefix),
            $this->findings
        );
    }

    /**
     * Get the number of findings per ruleId.
     *
     * @return array<string,int>
     */
    public function countByRuleId(): array
    {
        $secrets = [];
        foreach ($this->findings as $finding) {
            $ruleId = isset($finding['ruleId']) && is_scalar($finding['ruleId'])
                ? (string) $finding['ruleId']
                : 'unknown';
            $secrets[$ruleId] = ($secrets[$ruleId] ?? 0) + 1;
        }

        return $secrets;
    }

    /**
     * Get the total number of findings.
     */
    public function count(): int
    {
        return count($this->findings);
    }

    /**
     * Make the location URIs of a finding relative to a given prefix.
     *
     * @param array<string,mixed> $finding
     *
     * @return array<string,mixed>
     */
    private static function stripLocationPrefix(array $finding, string $prefix): array
    {
        $locations = $finding['locations'] ?? null;
        if (!is_array($locations)) {
            return $finding;
        }

        foreach ($locations as $index => $location) {
            if (!is_array($location)) {
                continue;
            }
            $physicalLocation = $location['physicalLocation'] ?? null;
            if (!is_array($physicalLocation)) {
                continue;
            }
            $artifactLocation = $physicalLocation['artifactLocation'] ?? null;
            if (!is_array($artifactLocation)) {
                continue;
            }
            $uri = $artifactLocation['uri'] ?? null;
            if (!is_string($uri) || !str_starts_with($uri, $prefix)) {
                continue;
            }
            $locations[$index]['physicalLocation']['artifactLocation']['uri'] = substr($uri, strlen($prefix));
        }
        $finding['locations'] = $locations;

        return $finding;
    }
}
