# Composer dependency analyser

This package aims to detect composer dependency issues in your project.

It detects **shadowed composer dependencies** and **unused composer dependencies** similar to other tools, but **MUCH faster**:

| Project                                   | Dead<br/>dependency | Shadow<br/>dependency  | Misplaced<br/>in `require` | Misplaced<br/> in `require-dev` | Time*      |
|-------------------------------------------|---------------------|------------------------|--------------------------|-------------------------------|------------|
| maglnet/composer-require-checker          | ❌                   | ✅                     | ❌                         |  ❌                             | 124 secs   |
| icanhazstring/composer-unused             | ✅                   | ❌                     | ❌                         |  ❌                             | 72 secs    |
| **shipmonk/composer-dependency-analyser** | ✅                   | ✅                     | ✅                         |  ✅                             | **3 secs** |

<sup><sub>\*Time measured on codebase with ~13 000 files</sub></sup>


This means you can safely add this tool to CI without wasting resources.

## Installation:

```sh
composer require --dev shipmonk/composer-dependency-analyser
```

*Note that this package itself has **zero composer dependencies.***

## Usage:

```sh
composer dump-autoload --classmap-authoritative # we use composer's autoloader to detect which class belongs to which package
vendor/bin/composer-dependency-analyser
```

Example output:
```txt

Found shadow dependencies!
(those are used, but not listed as dependency in composer.json)

  • nette/utils
    e.g. Nette\Utils\Strings in app/Controller/ProductController.php:24 (+ 6 more)

Found unused dependencies!
(those are listed in composer.json, but no usage was found in scanned paths)

  • nette/utils

```

You can add `--verbose` to see more example classes & usages.

## Detected issues:
This tool reads your `composer.json` and scans all paths listed in both `autoload` sections while analysing:

### Shadowed dependencies
  - Those are dependencies of your dependencies, which are not listed in `composer.json`
  - Your code can break when your direct dependency gets updated to newer version which does not require that shadowed dependency anymore
  - You should list all those classes within your dependencies
  - Ignorable by `--ignore-shadow-deps` or more granularly by `--config`

### Unused dependencies
  - Any non-dev dependency is expected to have at least single usage within the scanned paths
  - To avoid false positives here, you might need to adjust scanned paths or ignore some packages by `--config`
  - Ignorable by `--ignore-unused-deps` or more granularly by `--config`

### Dev dependencies in production code
  - For libraries, this is risky as your users might not have those installed
  - For applications, it can break once you run it with `composer install --no-dev`
  - You should move those from `require-dev` to `require`
  - Ignorable by `--ignore-dev-in-prod-deps` or more granularly by `--config`

### Prod dependencies used only in dev paths
  - For libraries, this miscategorization can lead to uselessly required dependencies for your users
  - You should move those from `require` to `require-dev`
  - Ignorable by `--ignore-prod-only-in-dev-deps` or more granularly by `--config`

### Unknown classes
  - Any class missing in composer classmap gets reported as we cannot say if that one is shadowed or not
  - Ignorable by `--ignore-unknown-classes` or more granularly by `--config`

It is expected to run this tool in root of your project, where the `composer.json` is located.
If you want to run it elsewhere, you can use `--composer-json=path/to/composer.json` option.

Currently, it only supports those `composer.json` autoload sections: `psr-4`, `psr-0`, `files`.

## Configuration:
You can provide custom path to config file by `--config=path/to/config.php` where the config file is PHP file returning `ShipMonk\ComposerDependencyAnalyser\Config\Configuration` object.
It gets loaded automatically if it is located in cwd as `composer-dependency-analyser.php`.
Here is example of what you can do:

```php
<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

return $config
    // disable scanning autoload & autoload-dev paths from composer.json
    // with such option, you should add custom paths by addPathToScan() or addPathsToScan()
    ->disableComposerAutoloadPathScan()

    // report unused dependencies even for dev packages
    // dev packages are often used only in CI, so this is not enabled by default
    // but you may want to ignore those packages manually to be sure
    ->enableAnalysisOfUnusedDevDependencies()

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
    // for multiple packages at once, use ignoreErrorsOnPackages()
    ->ignoreErrorsOnPackage('symfony/polyfill-php73', [ErrorType::UNUSED_DEPENDENCY])

    // allow using classes not present in composer's autoloader
    // e.g. a library may conditionally support some feature only when Memcached is available
    ->ignoreUnknownClasses(['Memcached'])

    // allow using classes not present in composer's autoloader by regex
    // e.g. when you want to ignore whole namespace of classes
    ->ignoreUnknownClassesRegex('~^PHPStan\\.*?~')

    // force certain classes to be treated as used
    // handy when dealing with dependencies in non-php files (e.g. DIC config), see example below
    // beware that those are not validated and do not even trigger unknown class error
    ->addForceUsedSymbols($classesExtractedFromNeonJsonYamlXmlEtc)
;
```

All paths are expected to exist. If you need some glob functionality, you can do it in your config file and pass the expanded list to e.g. `ignoreErrorsOnPaths`.

### Detecting classes from non-php files:

Simplest fuzzy search for classnames within your yaml/neon/xml/json files might look like this:

```php
$classNameRegex = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*'; // https://www.php.net/manual/en/language.oop5.basic.php
$dicFileContents = file_get_contents(__DIR__ . '/config/services.yaml');

preg_match_all(
    "~$classNameRegex(?:\\\\$classNameRegex)+~", // at least one backslash
    $dicFileContents,
    $matches
); // or parse the yaml properly

$config->addForceUsedSymbols($matches[1]); // possibly filter by class_exists || interface_exists
```

Similar approach should help you to avoid false positives in unused dependencies due to the usages being present in e.g. DIC config files only.
Another approach for DIC-only usages is to scan the generated php file, but that gave us worse results.

## Limitations:
- Extension dependencies are not analysed (e.g. `ext-json`)
- Files without namespace has limited support
  - Only classes with use statements and FQNs are detected
- Function and constant usages are not analysed
  - Therefore, if some package contains only functions, it will be reported as unused

-----

Despite those limitations, our experience is that this composer-dependency-analyser works much better than composer-unused and composer-require-checker.

## Contributing:
- Check your code by `composer check`
- Autofix coding-style by `composer fix:cs`
- All functionality must be tested

## Supported PHP versions
- PHP 7.2 - 8.3
