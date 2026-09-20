<?php

declare(strict_types=1);

use Barama\Core\Request;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/runtime.php';

$cases = [
    [
        'label' => 'root property route',
        'script' => '/index.php',
        'uri' => '/properties?type=house',
        'expected' => '/properties',
    ],
    [
        'label' => 'subdirectory property route',
        'script' => '/barama/index.php',
        'uri' => '/barama/properties?type=house',
        'expected' => '/properties',
    ],
    [
        'label' => 'subdirectory root route',
        'script' => '/barama/index.php',
        'uri' => '/barama/',
        'expected' => '/',
    ],
    [
        'label' => 'subdirectory dynamic company route',
        'script' => '/barama/index.php',
        'uri' => '/barama/company/acme-development',
        'expected' => '/company/acme-development',
    ],
    [
        'label' => 'root dynamic project route',
        'script' => '/index.php',
        'uri' => '/projects/riverside-residences',
        'expected' => '/projects/riverside-residences',
    ],
];

foreach ($cases as $case) {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['SCRIPT_NAME'] = $case['script'];
    $_SERVER['REQUEST_URI'] = $case['uri'];
    $_GET = [];
    $_POST = [];
    $_FILES = [];

    $request = Request::capture();

    if ($request->path() !== $case['expected']) {
        throw new RuntimeException(
            $case['label']
            . ': expected '
            . $case['expected']
            . ', received '
            . $request->path()
        );
    }
}

fwrite(
    STDOUT,
    '[PASS] HTTP routing smoke: root and subdirectory request paths normalize to canonical application routes.'
    . PHP_EOL
);
