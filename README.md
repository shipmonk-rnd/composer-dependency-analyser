# Composer dependency analyser

This package aims to detect composer dependency issues in your project, fast!

For example, it detects shadowed depencencies similar to [maglnet/composer-require-checker](https://github.com/maglnet/ComposerRequireChecker), but **much faster**:

| Project                               | Analysis of 13k files |
|---------------------------------------|-----------------------|
| shipmonk/composer-dependency-analyser | 2 secs                |
| maglnet/composer-require-checker      | 124 secs              |

## Installation:

```sh
composer require --dev shipmonk/composer-dependency-analyser
```

*Note that this package itself has zero composer dependencies.*

## Usage:

```sh
composer dump-autoload -o # we use composer's autoloader to detect which class belongs to which package
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

You can add `--verbose` flag to see first usage of each class.

## What it does:
This tool reads your `composer.json` and scans all paths listed in both `autoload` sections while analysing:

- Shadowed dependencies
  - Those are dependencies of your dependencies, which are not listed in `composer.json`
  - Your code can break when your direct dependency gets updated to newer version which does not require that shadowed dependency anymore
  - You should list all those classes within your dependencies
- Dev dependencies used in production code
  - Your code can break once you run your application with `composer install --no-dev`
  - You should move those to `require` from `require-dev`
- Unknown classes
  - If some found usage is not found within composer classmap, it is reported (as we cannot say if that one is shadowed or not)
  - This might be expected in some cases, so you can disable this behaviour by `--ignore-unknown-classes`

It is expected to run this tool in root of your project, where the `composer.json` is located.
If you want to run it elsewhere, you can use `--composer-json=path/to/composer.json` option.

Currently, it only supports those autoload sections: `psr-4`, `psr-0`, `files`.

## Future scope:
- Detecting dead dependencies

## Limitations:
- Files without namespace has limited support
  - Only classes with use statements and FQNs are detected

## Contributing:
- Check your code by `composer check`
- Autofix coding-style by `composer fix:cs`
- All functionality must be tested
