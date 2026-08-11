<?php declare(strict_types = 1);

// second vendor dir, installs provided/elsewhere for real, see ComposerInstalledTest

return [
    'versions' => [
        'provided/elsewhere' => [
            'pretty_version' => '1.0.0',
            'version' => '1.0.0.0',
            'reference' => null,
            'type' => 'library',
            'install_path' => __DIR__ . '/../provided/elsewhere',
            'aliases' => [],
            'dev_requirement' => false,
        ],
    ],
];
