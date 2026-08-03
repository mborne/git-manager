<?php

namespace MBO\GitManager\Twig;

use MBO\GitManager\Helpers\SourceUrlHelpers;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose {@link SourceUrlHelpers} to the templates.
 */
final class SourceUrlExtension extends AbstractExtension
{
    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('source_file_url', SourceUrlHelpers::getFileUrl(...)),
        ];
    }
}
