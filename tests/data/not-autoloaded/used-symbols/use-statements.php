<?php
namespace My\App;

use \PHPUnit\Framework\Exception;
use PHPUnit\Framework;
use PHPUnit\Framework\Constraint as ConstraintAlias;
use PHPUnit\Framework\ { Warning as WarningAlias, function assertArrayNotHasKey, Error };
use PHPUnit\Framework\Constraint\DirectoryExists, PHPUnit\Framework\Constraint\FileExists;
use function PHPUnit\Framework\ { assertArrayHasKey, assertEquals };

new Exception();
new WarningAlias();
new Error();
new Framework\OutputError();
new ConstraintAlias\IsNan();
new \PHPUnit\Framework\Constraint\IsFinite();
new DirectoryExists();
new FileExists();

trait Trait_ {
    public function originalMethod(): void {}
}

class Class_
{
    use Trait_ {
        originalMethod as newMethodName;
    }

    public function test() {
        $this->newMethodName();
    }

}

assertArrayNotHasKey('', []);
assertArrayHasKey('', []);
assertEquals('', '');
