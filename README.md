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

Basically, this tool extracts used symbols just from use statements and FQNs and compare those with your composer dependencies.

## Usage:

```sh
composer dump-autoload -o
vendor/bin/composer-dependency-analyser src
```

## Future scope:
- Detecting dead dependencies

## Contributing:
- Check your code by `composer check`
- Autofix coding-style by `composer fix:cs`
- All functionality must be tested
