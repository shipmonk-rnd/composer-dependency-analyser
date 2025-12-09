<?php declare(strict_types = 1);

use ShipMonk\ComposerDependencyAnalyser\Analyser;
use ShipMonk\ComposerDependencyAnalyser\Cli;
use ShipMonk\ComposerDependencyAnalyser\Initializer;
use ShipMonk\ComposerDependencyAnalyser\UsedSymbolExtractor;
use ShipMonk\CoverageGuard\Config;
use ShipMonk\CoverageGuard\Hierarchy\ClassMethodBlock;
use ShipMonk\CoverageGuard\Hierarchy\CodeBlock;
use ShipMonk\CoverageGuard\Rule\CoverageError;
use ShipMonk\CoverageGuard\Rule\CoverageRule;
use ShipMonk\CoverageGuard\Rule\InspectionContext;

$config = new Config();
$config->addRule(new class implements CoverageRule {

    public function inspect(
        CodeBlock $codeBlock,
        InspectionContext $context,
    ): ?CoverageError
    {
        if (!$codeBlock instanceof ClassMethodBlock) {
            return null;
        }

        if ($codeBlock->getExecutableLinesCount() < 5) {
            return null;
        }

        $methodReflection = $context->getMethodReflection();
        if ($methodReflection === null) {
            return null;
        }

        $coverage = $codeBlock->getCoveragePercentage();
        $classReflection = $methodReflection->getDeclaringClass();
        $requiredCoverage = $this->getRequiredCoverage($classReflection);

        if ($codeBlock->getCoveragePercentage() < $requiredCoverage) {
            return CoverageError::create("Method <white>{$codeBlock->getMethodName()}</white> requires $requiredCoverage% coverage, but has only $coverage%.");
        }

        return null;
    }

    /**
     * @param ReflectionClass<object> $classReflection
     */
    private function getRequiredCoverage(ReflectionClass $classReflection): int
    {
        $isPoor = in_array($classReflection->getName(), [
            Initializer::class,
        ], true);

        $isCore = in_array($classReflection->getName(), [
            Cli::class,
            Analyser::class,
            UsedSymbolExtractor::class,
        ], true);

        return match (true) {
            $isCore => 80,
            $isPoor => 40,
            default => 60,
        };
    }

});

return $config;
