<?php

use App\Clazz as AppClazz;
use Regular\Package as Intermediate;
use Regular\Package\Clazz;
use Shadow\Package\Clazz as ShadowClazz;
use Dev\Package\Clazz as DevClazz;
use DateTimeImmutable;
use DateTimeInterface;

new \Unknown\Clazz(); // reported as unknown
new AppClazz();
new Intermediate\Clazz();
new Clazz();

new DevClazz(); // reported as dev
new DateTimeImmutable();

\Composer\InstalledVersions::getInstalledPackages();




ShadowClazz::class;
