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
new ShadowClazz(); // reported as shadow
new DevClazz(); // reported as dev
new DateTimeImmutable();

echo \DIRECTORY_SEPARATOR;
echo \strlen('');

\Composer\InstalledVersions::getInstalledPackages();

ShadowClazz::class; // second usage to test
