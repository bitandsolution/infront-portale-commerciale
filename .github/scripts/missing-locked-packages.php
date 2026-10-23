<?php

/*
| Prints the packages required by composer.json that composer.lock does not contain yet,
| separated by spaces (empty output: the lock is complete).
|
| Used by the CI (docs/dev/ci.md §4): packagist.org is not reachable from the development
| sessions, so a pull request adds the package to composer.json and the CI resolves only
| that package (composer update <packages> --with-dependencies) and commits the lock.
*/

$root = dirname(__DIR__, 2);

$composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$lock = json_decode((string) file_get_contents($root.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);

$required = array_keys(($composer['require'] ?? []) + ($composer['require-dev'] ?? []));
$required = array_filter($required, fn (string $name): bool => str_contains($name, '/'));

$locked = array_map(
    fn (array $package): string => strtolower($package['name']),
    array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []),
);

$missing = array_filter($required, fn (string $name): bool => ! in_array(strtolower($name), $locked, true));

echo implode(' ', $missing);
