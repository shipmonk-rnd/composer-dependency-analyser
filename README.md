# Composer dependency analyser

This package aims to detect shadowed composer dependencies in your project, fast!
See comparison with existing projects:

| Project                                                                               | Analysis of 13k files |
|---------------------------------------------------------------------------------------|-----------------------|
| shipmonk/composer-dependency-analyser                                                 | 2 secs                |
| [maglnet/composer-require-checker](https://github.com/maglnet/ComposerRequireChecker) | 124 secs              |

## Installation:

```sh
composer require --dev shipmonk/composer-dependency-analyser
```

## Preconditions:
- To achieve such performance, your project needs follow some `use statements` limitations
- Disallowed approaches:
  - Partial use statements, `use Doctrine\ORM\Mapping as ORM;` + `#[ORM\Entity]`
  - Multiple use statements `use Foo, Bar;`
  - Bracketed use statements `use Foo\{Bar, Baz};`
  - Bracketed namespaces `namespace Foo { ... }`

All this can be ensured by [slevomat/coding-standard](https://github.com/slevomat/coding-standard) with following config:

```xml
<?xml version="1.0"?>
<ruleset>
    <rule ref="SlevomatCodingStandard.Namespaces.DisallowGroupUse"/>
    <rule ref="SlevomatCodingStandard.Namespaces.NamespaceDeclaration"/>
    <rule ref="SlevomatCodingStandard.Namespaces.MultipleUsesPerLine"/>
    <rule ref="SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly">
        <properties>
            <property name="allowPartialUses" value="false"/>
        </properties>
    </rule>
</ruleset>
```

Basically, this tool extracts used symbols just from use statements and FQNs (PHP8+) and compare those with your composer dependencies.

## Usage:

```sh
composer dump-autoload -o # we use composer's autoloader to detect which class belongs to which package
vendor/bin/composer-dependency-analyser src
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

## Shadow dependency risks
You are not in control of dependencies of your dependencies, so your code can break if you rely on such transitive dependency and your direct dependency will be updated to newer version which does not require that transitive dependency anymore.

Every used class should be listed in your `require` (or `require-dev`) section of `composer.json`.

## Future scope:
- Detecting dead dependencies
- Detecting dev dependencies used in production code
- Lowering number of preconditions

## Contributing:
- Check your code by `composer check`
- Autofix coding-style by `composer fix:cs`
- All functionality must be tested
