<?php

declare(strict_types=1);

/**
 * Compare two offline, independently restored copies of the ORIGINAL legacy
 * database. This does not copy billing tokens, MFA secrets, passwords or PII.
 * No row contents or content fingerprints are printed.
 */
if (PHP_SAPI !== 'cli' || ($argv[1] ?? null) !== '--compare-restores'
    || count($argv) !== 2 || getenv('APP_ENV') !== 'testing'
    || getenv('BARAMA_REHEARSAL_APPROVED') !== 'TWO_DISPOSABLE_CLONES_VERIFIED'
    || getenv('BARAMA_RESTORED_OFFLINE_VERIFIED') !== 'yes') {
    fwrite(STDERR, "RESTORE_DENIED=PRIVATE_TESTING_ONLY\n");
    exit(3);
}

$groups = [
    'financial' => [
        'subscription_plans','company_subscriptions','billing_invoices',
        'billing_invoice_items','billing_transactions','billing_coupons',
        'billing_coupon_redemptions','billing_tax_rules',
        'billing_payment_providers','billing_webhook_events',
        'billing_payment_methods','invoice_templates',
    ],
    'accounts_and_security' => [
        'users','roles','permissions','user_roles','role_permissions',
        'user_profiles','company_members','company_invitations',
        'user_security_factors','security_audit_events','login_attempts',
        'password_reset_tokens','email_change_tokens',
        'email_verification_tokens',
    ],
    'communications' => [
        'notification_preferences','notifications','email_outbox',
        'email_outbox_events','saved_search_matches','newsletter_subscribers',
        'communication_email_templates','communication_email_template_variants',
        'communication_email_template_revisions',
    ],
];

function privateDb(string $kind): PDO
{
    $prefix='BARAMA_'.$kind.'_';
    $db=(string)getenv($prefix.'DB');
    $host=(string)getenv($prefix.'HOST');
    $user=(string)getenv($prefix.'USER');
    $password=getenv($prefix.'PASSWORD');
    $port=(int)(getenv($prefix.'PORT') ?: 3306);
    if (!in_array($host,['127.0.0.1','localhost'],true)
        || preg_match('/^barama_legacy_(qa|rehearsal)_[a-z0-9_]{4,32}$/D',$db)!==1
        || $user==='' || !is_string($password) || $password===''
        || $port<1 || $port>65535) {
        throw new RuntimeException('Private clone configuration rejected.');
    }
    $pdo=new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $host,$port,$db),$user,$password,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT=>5,
            PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>false,
        ]);
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    $identity=$pdo->query('SELECT DATABASE()');
    $actualDb=$identity->fetchColumn();
    $identity->closeCursor();
    if ($actualDb!==$db) {
        throw new RuntimeException('Clone schema mismatch.');
    }
    return $pdo;
}

function safeSchema(PDO $db,array $allowed): array
{
    $columns=$db->query(
        'SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,ORDINAL_POSITION
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION'
    )->fetchAll(PDO::FETCH_ASSOC);
    $keys=$db->query(
        "SELECT TABLE_NAME,COLUMN_NAME,ORDINAL_POSITION
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA=DATABASE() AND CONSTRAINT_NAME='PRIMARY'
         ORDER BY TABLE_NAME,ORDINAL_POSITION"
    )->fetchAll(PDO::FETCH_ASSOC);
    $primary=[];
    foreach($keys as $row) {
        $primary[$row['TABLE_NAME']][]=$row['COLUMN_NAME'];
    }
    $schema=[];
    foreach($columns as $row) {
        $table=$row['TABLE_NAME'];
        if(in_array($table,$allowed,true)) {
            $schema[$table]['columns'][]=[
                $row['COLUMN_NAME'],$row['COLUMN_TYPE'],$row['IS_NULLABLE'],
                $row['COLUMN_DEFAULT'],$row['EXTRA'],(int)$row['ORDINAL_POSITION'],
            ];
        }
    }
    foreach($schema as $table=>&$spec) {
        $spec['primary']=$primary[$table]??[];
    }
    unset($spec);
    return $schema;
}

/** HMAC is generated with a fresh in-process key; only PASS/FAIL leaves it. */
function signedTable(PDO $db,string $table,array $schema,string $secret): array
{
    if(preg_match('/^[a-z][a-z0-9_]*$/D',$table)!==1
        || count($schema['columns'])===0 || $schema['primary']===[]) {
        throw new RuntimeException('No safe deterministic table order.');
    }
    foreach($schema['primary'] as $field) {
        if(preg_match('/^[a-z][a-z0-9_]*$/D',$field)!==1) {
            throw new RuntimeException('Unsafe primary key.');
        }
    }
    $stmt=$db->query('SELECT * FROM '.$table.' ORDER BY '
        .implode(',',$schema['primary']));
    $h=hash_init('sha256',HASH_HMAC,$secret);
    $rows=0;
    while(($record=$stmt->fetch(PDO::FETCH_ASSOC))!==false) {
        if(++$rows>2000000) {
            throw new RuntimeException('Bounded row count exceeded.');
        }
        foreach($record as $field=>$value) {
            hash_update($h,pack('N',strlen($field)).$field);
            if($value===null) {
                hash_update($h,"\x00");
            } else {
                $value=(string)$value;
                hash_update($h,"\x01".pack('N',strlen($value)).$value);
            }
        }
        hash_update($h,"\xFF");
    }
    $stmt->closeCursor();
    return [$rows,hash_final($h)];
}

try {
    if(getenv('BARAMA_SOURCE_DB')===getenv('BARAMA_RESTORED_DB')) {
        throw new RuntimeException('Independent restored clone required.');
    }
    $a=privateDb('SOURCE');
    $b=privateDb('RESTORED');
    $allowed=array_values(array_unique(array_merge(...array_values($groups))));
    $schemas=[safeSchema($a,$allowed),safeSchema($b,$allowed)];
    $secret=random_bytes(32);
    $report=[];
    $mismatched=[];
    $missing=[];
    foreach($groups as $group=>$names) {
        foreach($names as $table) {
            $left=$schemas[0][$table]??null;
            $right=$schemas[1][$table]??null;
            if($left===null && $right===null) {
                $report[$group][$table]=['state'=>'MISSING_BOTH'];
                $missing[]=$table;
                continue;
            }
            if($left===null || $right===null || $left!==$right) {
                $report[$group][$table]=['state'=>'SCHEMA_MISMATCH'];
                $mismatched[]=$table;
                continue;
            }
            if($left['primary']===[]) {
                $report[$group][$table]=['state'=>'NO_PRIMARY_KEY'];
                $mismatched[]=$table;
                continue;
            }
            $x=signedTable($a,$table,$left,$secret);
            $y=signedTable($b,$table,$right,$secret);
            $ok=$x[0]===$y[0] && hash_equals($x[1],$y[1]);
            $report[$group][$table]=[
                'state'=>$ok?'MATCH':'CONTENT_MISMATCH',
                'original_rows'=>$x[0],'restored_rows'=>$y[0],
            ];
            if(!$ok) {
                $mismatched[]=$table;
            }
        }
    }
    $mustExist=['billing_invoices','billing_transactions','user_roles',
        'user_security_factors','security_audit_events'];
    $requiredMissing=array_values(array_intersect($mustExist,$missing));
    $pass=$mismatched===[] && $requiredMissing===[];
    echo json_encode([
        'audit'=>'barama-sensitive-two-restore-comparison',
        'source'=>'two_distinct_offline_legacy_restores',
        'mode'=>'READ_ONLY',
        'categories'=>$report,
        'mismatch_table_names'=>array_values(array_unique($mismatched)),
        'missing_required_tables'=>$requiredMissing,
        'values_or_hashes_printed'=>false,
        'secrets_or_financial_records_migrated'=>false,
        'backup_consistency'=>$pass?'TWO_RESTORES_MATCH':'BLOCKED',
        'production_release'=>'BLOCKED_PENDING_OPERATIONAL_DATA_ADAPTERS',
    ],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
    exit($pass?0:2);
} catch(Throwable $e) {
    fwrite(STDERR,"RESTORE_FAILED=PRIVATE_DATA_OR_SCHEMA_GATE\n");
    exit(4);
}
