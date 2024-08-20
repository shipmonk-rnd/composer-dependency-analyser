<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Exception;

class AbortException extends RuntimeException
{

    public function __construct()
    {
        parent::__construct('');
    }

}
