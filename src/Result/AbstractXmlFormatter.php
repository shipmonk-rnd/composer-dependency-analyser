<?php declare(strict_types = 1);

namespace ShipMonk\ComposerDependencyAnalyser\Result;

use DOMDocument;
use DOMElement;
use ShipMonk\ComposerDependencyAnalyser\Printer;

abstract class AbstractXmlFormatter
{

    /**
     * @var Printer
     */
    protected $printer;

    /**
     * @var DOMDocument
     */
    protected $document;

    /**
     * @var DOMElement
     */
    protected $rootElement;

    public function __construct(Printer $printer, bool $verbose)
    {
        $this->printer = $printer;

        $this->document = new DOMDocument('1.0', 'UTF-8');
        $this->document->formatOutput = $verbose;
    }

}
