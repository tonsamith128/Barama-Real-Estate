<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/application/app/Services/ApprovedCompanyMediaEditPolicy.php';

use Barama\Services\ApprovedCompanyMediaEditPolicy as Policy;

$cases = [
    ['approved company / authorized admin', ['company', 'approved', true, true, true, true], true],
    ['approved company / developer', ['company', 'approved', false, true, true, true], false],
    ['approved company / no dashboard', ['company', 'approved', true, false, true, true], false],
    ['approved company / no company permission', ['company', 'approved', true, true, false, true], false],
    ['approved company / no media permission', ['company', 'approved', true, true, true, false], false],
    ['company pending review', ['company', 'pending', true, true, true, true], false],
    ['company suspended', ['company', 'suspended', true, true, true, true], false],
    ['company rejected', ['company', 'rejected', true, true, true, true], false],
    ['published project', ['project', 'published', true, true, true, true], false],
    ['approved property', ['property', 'approved', true, true, true, true], false],
    ['published listing', ['listing', 'published', true, true, true, true], false],
];

foreach ($cases as [$name, $args, $expected]) {
    if (Policy::allows(...$args) !== $expected) {
        fwrite(STDERR, 'FAILED: ' . $name . PHP_EOL);
        exit(1);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'APPROVED_COMPANY_MEDIA_POLICY=PASS' . PHP_EOL;
