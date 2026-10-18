<?php
declare(strict_types=1);

/** Phase 4E3: compare actual migrated Barama CI schema to disposable MySQL restore.
 * Rootless, non-mutating, GitHub CI-only. Never handles customer databases.
 */
function abortCompare(string $reason): never {
    fwrite(STDERR, 'STOP='.$reason."\n");
    exit(1);
}
function ciScope(): bool {
    return PHP_SAPI==='cli'
      && getenv('GITHUB_ACTIONS')==='true'
      && getenv('GITHUB_REPOSITORY')==='tonsamith128/Barama-Real-Estate'
      && getenv('GITHUB_JOB')==='foundation-qa'
      && getenv('APP_ENV')==='testing'
      && getenv('DB_DATABASE')==='barama_test'
      && getenv('DB_HOST')==='127.0.0.1'
      && getenv('DB_PORT')==='3306'
      && getenv('PACKAGE_DB_DATABASE')==='barama_package_test'
      && getenv('PACKAGE_DB_USERNAME')==='root'
      && getenv('PACKAGE_DB_HOST')==='127.0.0.1'
      && getenv('PACKAGE_DB_PORT')==='3306'
      && getenv('BARAMA_CI_REAL_SCHEMA_RECOVERY')==='ALLOW_MIGRATED_CI_DATABASE_COPY_ONLY'
      && preg_match('/^[a-f0-9]{12,64}$/D', (string)getenv('MYSQL_SERVICE_CONTAINER_ID'))===1
      && strlen((string)getenv('PACKAGE_DB_PASSWORD'))>0;
}
if (($argv[1]??'')==='--guard') {
    if (ciScope()) abortCompare('GUARD_CALLED_WITH_CI_AUTHORIZATION');
    echo "NON_CI_RUNTIME=REJECTED\nDATABASE_CONNECTIONS=NO\nDATABASE_WRITES=NO\nAUTO_INSTALL=DISABLED\n";
    exit(0);
}
if (($argv[1]??'')!=='--compare' || count($argv)!==3 || !ciScope()) {
    abortCompare('CI_DISPOSABLE_MYSQL_ONLY');
}
$source='barama_test';
$target=$argv[2];
if (preg_match('/^barama_ci4e_real_[a-f0-9]{16}$/D', $target)!==1) {
    abortCompare('DESTINATION_SCOPE_INVALID');
}
try {
    $pdo=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
      'root', (string)getenv('PACKAGE_DB_PASSWORD'), [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
        PDO::ATTR_STRINGIFY_FETCHES=>true,
        PDO::ATTR_TIMEOUT=>10,
      ]);
} catch (Throwable) {
    abortCompare('DISPOSABLE_CI_MYSQL_UNAVAILABLE');
}
function metadata(PDO $pdo, string $sql, string $name): array {
    $st=$pdo->prepare($sql);
    $st->execute([$name]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
$metadataQueries=[
 'TABLES'=>'SELECT TABLE_NAME,TABLE_TYPE,ENGINE,COALESCE(TABLE_COLLATION, \'\') AS COLLATION_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME',
 'COLUMNS'=>'SELECT TABLE_NAME,ORDINAL_POSITION,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COALESCE(COLLATION_NAME, \'\') AS COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME,ORDINAL_POSITION',
 'INDEXES'=>'SELECT TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE,INDEX_TYPE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX',
 'FOREIGN_KEYS'=>'SELECT CONSTRAINT_NAME,TABLE_NAME,REFERENCED_TABLE_NAME,UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? ORDER BY TABLE_NAME,CONSTRAINT_NAME',
 'FK_COLUMNS'=>'SELECT CONSTRAINT_NAME,TABLE_NAME,COLUMN_NAME,ORDINAL_POSITION,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION',
 'TRIGGERS'=>'SELECT TRIGGER_NAME,EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_TIMING,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=? ORDER BY TRIGGER_NAME',
 'ROUTINES'=>'SELECT ROUTINE_NAME,ROUTINE_TYPE,ROUTINE_DEFINITION,DTD_IDENTIFIER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=? ORDER BY ROUTINE_NAME',
 'EVENTS'=>'SELECT EVENT_NAME,EVENT_DEFINITION,STATUS FROM information_schema.EVENTS WHERE EVENT_SCHEMA=? ORDER BY EVENT_NAME',
 'VIEWS'=>'SELECT TABLE_NAME,VIEW_DEFINITION,CHECK_OPTION,SECURITY_TYPE FROM information_schema.VIEWS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME',
];
function quoted(string $identifier): string {
    if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $identifier)!==1) {
        abortCompare('UNTRUSTED_SQL_IDENTIFIER');
    }
    return chr(96).$identifier.chr(96);
}
try {
    $tables=metadata($pdo, $metadataQueries['TABLES'], $source);
    if (count($tables)<35 || count($tables)>500) abortCompare('MIGRATED_SCHEMA_TABLE_COUNT_INVALID');
    $names=[];
    foreach ($tables as $row) {
        if ($row['TABLE_TYPE']==='BASE TABLE') {
            $names[]=quoted((string)$row['TABLE_NAME']);
        }
    }
    if (!in_array(quoted('properties'),$names,true)
      || !in_array(quoted('users'),$names,true)
      || !in_array(quoted('schema_migrations'),$names,true)) {
        abortCompare('MIGRATED_SCHEMA_TABLES_MISSING');
    }
    $migrationCount=(int)$pdo->query('SELECT COUNT(*) FROM '.quoted($source).'.'.quoted('schema_migrations'))->fetchColumn();
    if ($migrationCount<10) abortCompare('MIGRATION_COUNT_TOO_LOW');
    foreach ($metadataQueries as $kind=>$sql) {
        if (metadata($pdo,$sql,$source)!==metadata($pdo,$sql,$target)) {
            abortCompare($kind.'_RESTORE_MISMATCH');
        }
        echo $kind."_METADATA=PASS\n";
    }
    $count=0;
    foreach ($names as $table) {
        $signatures=[];
        foreach ([$source,$target] as $db) {
            $stmt=$pdo->query('SELECT * FROM '.quoted($db).'.'.$table);
            $hashes=[];
            while (($row=$stmt->fetch(PDO::FETCH_ASSOC))!==false) {
                if (count($hashes)>=25000) abortCompare('CI_DATA_VOLUME_UNEXPECTED');
                $hashes[]=hash('sha256',serialize($row));
            }
            sort($hashes,SORT_STRING);
            $signatures[]=[count($hashes),hash('sha256',implode("\n",$hashes))];
        }
        if ($signatures[0]!==$signatures[1]) abortCompare('TABLE_ROW_DATA_RESTORE_MISMATCH');
        $count+=$signatures[0][0];
        if ($count>200000) abortCompare('CI_TOTAL_DATA_VOLUME_UNEXPECTED');
    }
    echo 'MIGRATIONS_COMPARED='.$migrationCount."\n";
    echo 'TABLES_COMPARED='.count($names)."\n";
    echo 'ROWS_COMPARED='.$count."\n";
    echo "ALL_APPLICATION_TABLE_ROWS=PASS\n";
    echo "SCHEMA_METADATA_EQUAL=PASS\n";
    echo "CUSTOMER_DATABASE_ACCESSED=NO\n";
    echo "LIVE_WEBSITE_WRITES=NO\n";
    echo "AUTO_INSTALL=DISABLED\n";
    echo "RESULT=PASS\n";
} catch (Throwable) {
    abortCompare('CI_APPLICATION_SCHEMA_COMPARISON_FAILED');
}
