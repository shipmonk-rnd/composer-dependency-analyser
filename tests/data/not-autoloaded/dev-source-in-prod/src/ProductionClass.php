<?php declare(strict_types = 1);

namespace App;

use App\DevOnlyClass;

class ProductionClass
{
    public function useDevClass(): void
    {
        $devClass = new DevOnlyClass();
        $devClass->devOnlyMethod();
    }
}
