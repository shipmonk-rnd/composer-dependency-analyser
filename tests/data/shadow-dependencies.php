<?php

use App\Clazz as AppClazz;
use Regular\Package as Intermediate; // precondition not met
use Regular\Package\Clazz;
use Shadow\Package\Clazz as ShadowClazz;
use Dev\Package\Clazz as DevClazz;
use DateTimeImmutable;
use DateTimeInterface;

new \Unknown\Clazz();
new AppClazz();
new Intermediate\Clazz();
new Clazz();
new ShadowClazz();
new DevClazz();
new DateTimeImmutable();

echo \DIRECTORY_SEPARATOR;
echo \strlen('');

\Composer\InstalledVersions::getInstalledPackages();
