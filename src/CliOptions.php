<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser;

class CliOptions
{

    /**
     * @var true|null
     */
    public $help = null;

    /**
     * @var true|null
     */
    public $verbose = null;

    /**
     * @var true|null
     */
    public $ignoreShadowDeps = null;

    /**
     * @var true|null
     */
    public $ignoreUnusedDeps = null;

    /**
     * @var true|null
     */
    public $ignoreDevInProdDeps = null;

    /**
     * @var true|null
     */
    public $ignoreProdOnlyInDevDeps = null;

    /**
     * @var true|null
     */
    public $ignoreUnknownClasses = null;

    /**
     * @var string|null
     */
    public $composerJson = null;

    /**
     * @var string|null
     */
    public $config = null;

    /**
     * @var string|null
     */
    public $dumpUsages = null;

    /**
     * @var bool|null
     */
    public $showAllUsages = null;

}
