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
    [
        'label' => 'root no-rewrite login route',
        'script' => '/index.php',
        'uri' => '/index.php?_route=%2Flogin',
        'get' => ['_route' => '/login'],
        'expected' => '/login',
    ],
    [
        'label' => 'subdirectory no-rewrite dynamic property route',
        'script' => '/barama/index.php',
        'uri' => '/barama/index.php?_route=%2Fproperties%2Fhouse-123&sort=latest',
        'get' => ['_route' => '/properties/house-123', 'sort' => 'latest'],
        'expected' => '/properties/house-123',
    ],
    [
        'label' => 'root no-rewrite homepage',
        'script' => '/index.php',
        'uri' => '/index.php?_route=%2F',
        'get' => ['_route' => '/'],
        'expected' => '/',
    ],
];

foreach ($cases as $case) {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['SCRIPT_NAME'] = $case['script'];
    $_SERVER['REQUEST_URI'] = $case['uri'];
    $_GET = $case['get'] ?? [];
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

// Assert the URL helper never creates rewrite-dependent routes in query mode.
$configItems = \Barama\Core\Config::all();
$configProperty = (new ReflectionClass(\Barama\Core\Config::class))
    ->getProperty('items');
try {
    $configProperty->setValue(array_replace($configItems, [
        'app' => array_replace((array) ($configItems['app'] ?? []), [
            'url' => 'https://example.test/barama',
            'routing_mode' => 'query',
        ]),
    ]));
    $urls = [
        '/' => 'https://example.test/barama/index.php?_route=%2F',
        '/login' => 'https://example.test/barama/index.php?_route=%2Flogin',
        '/properties?type=house' => 'https://example.test/barama/index.php?_route=%2Fproperties&type=house',
    ];
    foreach ($urls as $route => $expectedUrl) {
        if (url($route) !== $expectedUrl) {
            throw new RuntimeException('Built-in route URL mismatch: ' . $route);
        }
    }
} finally {
    $configProperty->setValue($configItems);
}

fwrite(
    STDOUT,
    '[PASS] HTTP routing smoke: clean and no-rewrite query routes work, including homepage and subdirectories.'
    . PHP_EOL
);
