<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

class CliOptions
{

    /**
     * @var true|null
     */
    public ?bool $version = null;

    /**
     * @var true|null
     */
    public ?bool $help = null;

    /**
     * @var true|null
     */
    public ?bool $verbose = null;

    /**
     * @var true|null
     */
    public ?bool $disableExtAnalysis = null;

    /**
     * @var true|null
     */
    public ?bool $ignoreShadowDeps = null;

    /**
     * @var true|null
     */
    public ?bool $ignoreUnusedDeps = null;

    /**
     * @var true|null
     */
    public ?bool $ignoreDevInProdDeps = null;

    /**
     * @var true|null
     */
    public ?bool $ignoreProdOnlyInDevDeps = null;

    /**
     * @var true|null
     */
    public ?bool $ignoreUnknownClasses = null;

    /**
     * @var true|null
     */
    public ?bool $ignoreUnknownFunctions = null;

    public ?string $composerJson = null;

    public ?string $config = null;

    public ?string $dumpUsages = null;

    public ?bool $showAllUsages = null;

    public ?string $format = null;

}
