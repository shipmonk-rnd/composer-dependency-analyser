<?php declare(strict_types=1);

function fetchDependents(string $packageName, int $page = 1): array {
    $url = "https://packagist.org/packages/{$packageName}/dependents.json?page={$page}";
    $data = json_decode(file_get_contents($url), true);
    return $data['packages'] ?? [];
}

function fetchPackageData(string $packageName): ?array {
    $url = "https://repo.packagist.org/p2/{$packageName}.json";
    $data = json_decode(file_get_contents($url), true);
    return $data['packages'][$packageName][0] ?? null;
}

function filterDependents(array $dependents): array {
    $result = [];
    foreach ($dependents as $dependent) {
        $packageName = $dependent['name'];
        $downloads = $dependent['downloads'] ?? 0;
        $stars = $dependent['favers'] ?? 0;

        if ($downloads < 100 || $stars < 5) {
            continue;
        }

        $packageData = fetchPackageData($packageName);
        if ($packageData) {
            $repository = $packageData['source']['url'] ?? '';

            $name = str_replace('/', '-', $packageName);
            $result[] = [
                'repo' => $repository,
                'name' => $name,
            ];
        }
    }
    return $result;
}

function outputYaml(array $items): void {
    foreach ($items as $item) {
        echo "  -\n";
        echo "    repo: {$item['repo']}\n";
        echo "    name: {$item['name']}\n";
    }
}

$packageName = 'shipmonk/composer-dependency-analyser';
$allDependents = [];
$page = 1;

do {
    $dependents = fetchDependents($packageName, $page);
    $allDependents = array_merge($allDependents, $dependents);
    $page++;
} while (count($dependents) > 0);

$filteredDependents = filterDependents($allDependents);
outputYaml($filteredDependents);
