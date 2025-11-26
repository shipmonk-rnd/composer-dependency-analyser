<?php declare(strict_types = 1);

namespace App;

class DevOnlyClass
{
    public function devOnlyMethod(): string
    {
        return 'This is only available in dev';
    }
}
