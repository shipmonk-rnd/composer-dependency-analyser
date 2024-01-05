# Composer dependency analyser

This package aims to detect composer dependency issues in your project.

It detects **shadowed dependencies** and **dead dependencies** similar to other tools, but **MUCH faster**:

| Project                                   | Dead dependency  | Shadow dependency | Time*       |
|-------------------------------------------|------------------|-------------------|-------------|
| maglnet/composer-require-checker          | ❌                | ✅                 | 124 secs |
| icanhazstring/composer-unused             | ✅                | ❌                 | 72 secs  |
| **shipmonk/composer-dependency-analyser** | ✅                | ✅                 | **3 secs** |

<sup><sub>\*Time measured on codebase with ~13 000 files</sub></sup>


This means you can safely add this tool to CI without wasting resources.

## Installation:

```sh
composer require --dev shipmonk/composer-dependency-analyser
```

*Note that this package itself has zero composer dependencies.*

## Usage:

```sh
composer dump-autoload --classmap-authoritative # we use composer's autoloader to detect which class belongs to which package
vendor/bin/composer-dependency-analyser
```

Example output:
```txt

Found shadow dependencies!
(those are used, but not listed as dependency in composer.json)

  • symfony/service-contracts
    e.g. Symfony\Contracts\Service\Attribute\Required in app/Controller/ProductController.php:24

Found unused dependencies!
(those are listed in composer.json, but no usage was found in scanned paths)

  • nette/utils

```

## Detected issues:
This tool reads your `composer.json` and scans all paths listed in both `autoload` sections while analysing:

- **Shadowed dependencies**
  - Those are dependencies of your dependencies, which are not listed in `composer.json`
  - Your code can break when your direct dependency gets updated to newer version which does not require that shadowed dependency anymore
  - You should list all those classes within your dependencies

- **Unused dependencies**
  - Any non-dev dependency is expected to have at least single usage within the scanned paths
  - To avoid false positives here, you might need to adjust scanned paths or ignore some packages by `--config`

- **Dev dependencies in production code**
  - For libraries, this is risky as your users might not have those installed
  - For applications, it can break once you run it with `composer install --no-dev`
  - You should move those from `require-dev` to `require`
  - If you want to ignore some packages here, use `--config`

- **Unknown classes**
  - Any class missing in composer classmap gets reported as we cannot say if that one is shadowed or not
  - This might be expected in some cases, so you can disable this behaviour by `--ignore-unknown-classes` or more granularly by `--config`

It is expected to run this tool in root of your project, where the `composer.json` is located.
If you want to run it elsewhere, you can use `--composer-json=path/to/composer.json` option.

Currently, it only supports those autoload sections: `psr-4`, `psr-0`, `files`.

## Configuration:
You can provide custom path to config file by `--config=path/to/config.php` where the config file is PHP file returning `ShipMonk\Composer\Config\Configuration` object.
Here is example of what you can do:

```php
<?php

use ShipMonk\Composer\Config\Configuration;
use ShipMonk\Composer\Enum\ErrorType;

$config = new Configuration();

return $config
    // disable scanning autoload & autoload-dev paths from composer.json
    // doing that, you must add custom paths by addPathToScan() or addPathsToScan()
    ->disableComposerAutoloadPathScan()

    // disable detection of dev dependencies in production code globally
    ->ignoreErrors([ErrorType::DEV_DEPENDENCY_IN_PROD])

    // overwrite file extensions to scan, defaults to 'php'
    ->setFileExtensions(['php'])

    // add extra path to scan
    // for multiple paths at once, use addPathsToScan()
    ->addPathToScan(__DIR__ . '/build', isDev: false)

    // ignore errors on specific paths
    // this can be handy when DIC container file was passed as extra path, but you want to ignore shadow dependencies there
    // for multiple paths at once, use ignoreErrorsOnPaths()
    ->ignoreErrorsOnPath(__DIR__ . '/cache/DIC.php', [ErrorType::SHADOW_DEPENDENCY])

    // ignore errors on specific packages
    // you might have various reasons to ignore certain errors
    // e.g. polyfills are often used in libraries, but those are obviously unused when running with latest PHP
    ->ignoreErrorsOnPackage('symfony/polyfill-php73', [ErrorType::UNUSED_DEPENDENCY])

    // allow using classes not present in composer's autoloader
    // e.g. a library may conditionally support some feature only when Memcached is available
    ->ignoreUnknownClasses(['Memcached'])

    // allow using classes not present in composer's autoloader by regex
    // e.g. when you want to ignore whole namespace of classes
    ->ignoreUnknownClassesRegex('~^PHPStan\\.*?~')
;
```

All paths are expected to exist. If you need some glob functionality, you can do it in your config file and pass the expanded list to e.g. `ignoreErrorsOnPaths`.

## Limitations:
- Files without namespace has limited support
  - Only classes with use statements and FQNs are detected

## Contributing:
- Check your code by `composer check`
- Autofix coding-style by `composer fix:cs`
- All functionality must be tested
