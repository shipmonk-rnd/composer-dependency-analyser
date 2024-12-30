<?php declare(strict_types = 1);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
// phpcs:disable Squiz.Functions.GlobalFunction.Found

/**
 * https://github.com/composer/packagist/blob/main/src/Controller/PackageController.php#L1282
 *
 * @return list<string>
 */
function fetchDependents(string $packageName, int $page = 1): array
{
    $url = "https://packagist.org/packages/{$packageName}/dependents.json?page={$page}&requires=require-dev";
    $data = json_decode(file_get_contents($url), true);
    $packages = $data['packages'] ?? [];

    $result = [];

    foreach ($packages as $package) {
        $packageName = $package['name'];
        $downloads = $package['downloads'] ?? 0;
        $abandoned = (bool) ($package['abandoned'] ?? false);

        if ($abandoned) {
            continue;
        }

        if ($downloads < 2000) {
            continue;
        }

        $result[] = $packageName;
    }

    return $result;
}

function fetchRepository(string $packageName): string
{
    $url = "https://repo.packagist.org/p2/{$packageName}.json";
    $data = json_decode(file_get_contents($url), true);
    $packageData = $data['packages'][$packageName][0] ?? null;

    preg_match('/github\.com\/([^\/]+)\/([^\/]+).git/', $packageData['source']['url'], $matches);
    [, $owner, $repo] = $matches;

    return "$owner/$repo";
}

/**
 * @param list<array{repo: string, cdaArgs?: string, composerArgs?: string}> $items
 */
function outputYaml(array $items): void
{
    foreach ($items as $item) {
        echo "  -\n";

        foreach ($item as $key => $value) {
            echo "    $key: " . trim($value) . "\n";
        }
    }
}

$packageName = 'shipmonk/composer-dependency-analyser';
$page = 1;
$result = [];

do {
    $dependents = fetchDependents($packageName, $page);

    foreach ($dependents as $dependent) {
        $repository = fetchRepository($dependent);
        $result[] = [
            'repo' => $repository,
        ];
    }

    $page++;
} while (count($dependents) > 0);

// manual adjustments for some repositories
$result[] = [
    'repo' => 'phpstan/phpstan-src',
    'cdaArgs' => '--config=build/composer-dependency-analyser.php',
];
$result[] = [
    'repo' => 'qossmic/deptrac-src',
];

foreach ($result as $index => &$item) {
    if (strpos($item['repo'], 'oveleon') === 0) {
        $item['composerArgs'] = '--no-plugins';
    }

    if (strpos($item['repo'], 'consistence') === 0 || strpos($item['repo'], 'VasekPurchart') === 0) {
        $item['cdaArgs'] = '--config=build/composer-dependency-analyser.config.php';
    }

    if (
        strpos($item['repo'], 'oveleon') === 0
        || strpos($item['repo'], 'contao') === 0
        || strpos($item['repo'], 'numero2') === 0
    ) {
        $item['cdaArgs'] = '--config=depcheck.php';
    }

    if (
        strpos($item['repo'], 'Setono') === 0
        || $item['repo'] === 'symplify/phpstan-rules'
        || $item['repo'] === 'contao-thememanager/core'
        || $item['repo'] === 'oveleon/contao-recommendation-bundle'
    ) {
        unset($result[$index]); // failing builds
    }

    if (
        $item['repo'] === 'wallabag/wallabag'
        || $item['repo'] === 'oveleon/contao-theme-compiler-bundle'
        || $item['repo'] === 'oveleon/contao-glossary-bundle'
        || $item['repo'] === 'oveleon/contao-cookiebar'
        || $item['repo'] === 'numero2/contao-marketing-suite'
        || $item['repo'] === 'inspirum/xml-php'
        || $item['repo'] === 'inspirum/balikobot-php'
        || $item['repo'] === 'idleberg/php-vite-manifest'
        || $item['repo'] === 'contao/contao'
        || $item['repo'] === 'phpstan/phpstan-src'
    ) {
        $item['cdaArgs'] = '--disable-ext-analysis ' . ($item['cdaArgs'] ?? '');
    }
}

usort($result, static function (array $a, array $b): int {
    return $a['repo'] <=> $b['repo'];
});

outputYaml($result);
