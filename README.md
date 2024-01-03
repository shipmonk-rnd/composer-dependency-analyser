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

  • Nette\Utils\Json (nette/utils)
  • Nette\Utils\JsonException (nette/utils)
  • Symfony\Contracts\Service\Attribute\Required (symfony/service-contracts)

```

You can add `--verbose` flag to see first usage (file & line) of each class.

## Detected issues:
This tool reads your `composer.json` and scans all paths listed in both `autoload` sections while analysing:

- **Shadowed dependencies**
  - Those are dependencies of your dependencies, which are not listed in `composer.json`
  - Your code can break when your direct dependency gets updated to newer version which does not require that shadowed dependency anymore
  - You should list all those classes within your dependencies
- **Unused dependencies**
  - Any non-dev dependency is expected to have at least single usage within the scanned paths
- **Dev dependencies in production code**
  - Your code can break once you run your application with `composer install --no-dev`
  - You should move those to `require` from `require-dev`
- **Unknown classes**
  - Any class missing in composer classmap gets reported as we cannot say if that one is shadowed or not
  - This might be expected in some cases, so you can disable this behaviour by `--ignore-unknown-classes`

It is expected to run this tool in root of your project, where the `composer.json` is located.
If you want to run it elsewhere, you can use `--composer-json=path/to/composer.json` option.

Currently, it only supports those autoload sections: `psr-4`, `psr-0`, `files`.

## Limitations:
- Files without namespace has limited support
  - Only classes with use statements and FQNs are detected

## Contributing:
- Check your code by `composer check`
- Autofix coding-style by `composer fix:cs`
- All functionality must be tested
