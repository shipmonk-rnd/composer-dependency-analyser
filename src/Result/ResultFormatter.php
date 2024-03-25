<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Result;

use ShipMonk\ComposerDependencyAnalyser\CliOptions;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

interface ResultFormatter
{

    public const VERBOSE_SHOWN_USAGES = 3;

    public function format(
        AnalysisResult $result,
        CliOptions $options,
        Configuration $configuration
    ): int;

}
