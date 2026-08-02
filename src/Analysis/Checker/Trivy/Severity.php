<?php

namespace MBO\GitManager\Analysis\Checker\Trivy;

/**
 * The severity levels of the vulnerabilities reported by trivy, from the most
 * to the least critical one.
 */
enum Severity: string
{
    case CRITICAL = 'CRITICAL';
    case HIGH = 'HIGH';
    case MEDIUM = 'MEDIUM';
    case LOW = 'LOW';

    /**
     * Get the rank of the severity, the most critical ones coming first.
     */
    public function getRank(): int
    {
        return array_search($this, self::cases(), true);
    }

    /**
     * Get the values of a list of severities (ex : ['HIGH','CRITICAL']).
     *
     * @param array<int,self> $severities
     *
     * @return array<int,string>
     */
    public static function toValues(array $severities): array
    {
        return array_map(fn (self $severity): string => $severity->value, $severities);
    }
}
