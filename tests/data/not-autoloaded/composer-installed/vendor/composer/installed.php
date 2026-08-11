<?php declare(strict_types = 1);

// mimics real vendor/composer/installed.php, see ComposerInstalledTest

return [
    'root' => [
        'name' => 'shipmonk/fixture',
        'pretty_version' => 'dev-master',
        'version' => 'dev-master',
        'reference' => null,
        'type' => 'library',
        'install_path' => __DIR__ . '/../../',
        'aliases' => [],
        'dev' => true,
    ],
    'versions' => [
        'regular/package' => [
            'pretty_version' => '1.0.0',
            'version' => '1.0.0.0',
            'reference' => null,
            'type' => 'library',
            'install_path' => __DIR__ . '/../regular/package',
            'aliases' => [],
            'dev_requirement' => false,
        ],
        'some/metapackage' => [
            'pretty_version' => '1.0.0',
            'version' => '1.0.0.0',
            'reference' => null,
            'type' => 'metapackage',
            'install_path' => null,
            'aliases' => [],
            'dev_requirement' => false,
        ],
        'psr/log-implementation' => [
            'dev_requirement' => false,
            'provided' => ['1.0|2.0|3.0'],
        ],
        // really installed, but some other installed package declares "provide" for this name too
        'psr/log' => [
            'pretty_version' => '3.0.2',
            'version' => '3.0.2.0',
            'reference' => null,
            'type' => 'library',
            'install_path' => __DIR__ . '/../psr/log',
            'aliases' => [],
            'dev_requirement' => false,
            'provided' => ['1.0.0'],
        ],
        'illuminate/log' => [
            'dev_requirement' => false,
            'replaced' => ['1.0.0'],
        ],
        'provided/elsewhere' => [
            'dev_requirement' => false,
            'provided' => ['1.0.0'],
        ],
    ],
];
