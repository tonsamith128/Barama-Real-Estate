<?php
declare(strict_types=1);

/** BARAMA_NATIVE_DEMO_LIMIT_PARITY_V109: no database, network or media writes. */
require dirname(__DIR__) . '/application/app/Services/DemoImportPackageV1.php';

$engine = new \Barama\Services\DemoImportPackageV1();
$validate = new ReflectionMethod($engine, 'validateContent');

function nativeLimitTest(bool $ok, string $label): void {
    if (!$ok) { fwrite(STDERR, "FAIL=$label\n"); exit(1); }
    echo "PASS=$label\n";
}
function nativeLimitReject(callable $run, string $code, string $label): void {
    try { $run(); }
    catch (\RuntimeException $e) {
        nativeLimitTest($e->getMessage() === $code, $label);
        return;
    }
    nativeLimitTest(false, $label);
}
function makeRows(int $count, int $offset=0): array {
    $rows = [];
    for ($i=1; $i <= $count; $i++) {
        $rows[] = ['id'=>$offset+$i, 'name'=>'Demo location '.$i];
    }
    return $rows;
}
$payload = [
    'schema'=>'barama-demo-content-v1',
    'components'=>['Locations'],
    'settings'=>[],
    'tables'=>['locations'=>makeRows(5001)]
];
$run = static fn (array $v, int $perTable=5000, int $total=12000, int $fields=80) =>
    $validate->invoke($engine, $v, [], $perTable, $total, $fields);

nativeLimitReject(fn()=>$run($payload), 'DEMO_TABLE_INVALID',
    'legacy_per_table_5000_still_enforced');
$run($payload, 10000, 24000, 100);
nativeLimitTest(true,'native_accepts_5001_publisher_rows');

$over = $payload;
$over['tables']['locations'] = makeRows(10001);
nativeLimitReject(fn()=>$run($over,10000,24000,100), 'DEMO_TABLE_INVALID',
    'native_rejects_more_than_10000_in_table');

$many = $payload;
$many['tables'] = [
    'locations'=>makeRows(9000),
    'amenities'=>makeRows(9000,9000),
    'companies'=>makeRows(6001,18000)
];
nativeLimitReject(fn()=>$run($many,10000,24000,100), 'DEMO_ROW_LIMIT',
    'native_rejects_more_than_24000_total');

$fields = ['id'=>1];
foreach (range(1,80) as $i) { $fields['field_'.$i] = 'value'; }
$wide = $payload;
$wide['tables']['locations'] = [$fields];
nativeLimitReject(fn()=>$run($wide), 'DEMO_ROW_INVALID',
    'legacy_field_count_80_still_enforced');
$run($wide,10000,24000,100);
nativeLimitTest(true,'native_accepts_81_fields');

nativeLimitReject(fn()=>$run($wide,10001,24000,100), 'DEMO_CONTENT_LIMIT_INVALID',
    'cannot_expand_native_bounds_past_inspector');

echo "NATIVE_DEMO_IMPORT_LIMIT_PARITY_V109=PASS\n";
