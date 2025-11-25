<?php
declare(strict_types=1);

namespace Test;

use function also_reported_and_not_defined;

// https://github.com/shipmonk-rnd/composer-dependency-analyser/issues/222
not_reported_or_defined();
\reported_and_not_defined();
also_reported_and_not_defined();
