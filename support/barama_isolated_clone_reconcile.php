<?php

declare(strict_types=1);

/**
 * INDEPENDENT, READ-ONLY verification of an already staged disposable clone.
 * No customer values, source UUIDs, old IDs, tokens or passwords are printed.
 * A successful audit is not a production cutover approval.
 */
if (PHP_SAPI !== 'cli' || count($argv)!==1
    || getenv('APP_ENV')!=='testing'
    || getenv('BARAMA_REHEARSAL_APPROVED')!=='TWO_DISPOSABLE_CLONES_VERIFIED') {
    fwrite(STDERR,"RECONCILE_DENIED=OFFLINE_CLONE_TESTING_ONLY\n");
    exit(3);
}
foreach (['SOURCE','TARGET'] as $role) {
    $database=(string)getenv('BARAMA_'.$role.'_DB');
    $host=(string)getenv('BARAMA_'.$role.'_HOST');
    $pattern=$role==='SOURCE'
        ? '/^barama_legacy_(?:qa|rehearsal)_[a-z0-9_]{4,32}$/D'
        : '/^barama_laravel_(?:qa|rehearsal)_[a-z0-9_]{4,32}$/D';
    if (preg_match($pattern,$database)!==1
        || !in_array($host,['127.0.0.1','localhost'],true)
        || !getenv('BARAMA_'.$role.'_USER')
        || !getenv('BARAMA_'.$role.'_PASSWORD')) {
        fwrite(STDERR,"RECONCILE_DENIED=INVALID_PRIVATE_CLONE_CONFIG\n");
        exit(3);
    }
}
function readonlyDb(string $role): PDO
{
    $db=new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        getenv('BARAMA_'.$role.'_HOST'),
        (int)(getenv('BARAMA_'.$role.'_PORT')?:3306),
        getenv('BARAMA_'.$role.'_DB')),
        getenv('BARAMA_'.$role.'_USER'),
        getenv('BARAMA_'.$role.'_PASSWORD'), [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
            PDO::ATTR_TIMEOUT=>5,
        ]);
    $db->exec('SET SESSION TRANSACTION READ ONLY');
    return $db;
}
function getRows(PDO $db,string $sql): array
{
    $rows=$db->query($sql)->fetchAll();
    if(count($rows)>100000) {
        throw new RuntimeException('Audit limit exceeded; use a bounded offline verifier.');
    }
    return $rows;
}
function oneCount(PDO $db,string $sql): int
{
    return (int)$db->query($sql)->fetchColumn();
}
try {
    $source=readonlyDb('SOURCE');
    $target=readonlyDb('TARGET');
    if ($source->query('SELECT DATABASE()')->fetchColumn()!==getenv('BARAMA_SOURCE_DB')
        || $target->query('SELECT DATABASE()')->fetchColumn()!==getenv('BARAMA_TARGET_DB')) {
        throw new RuntimeException('Schema identity mismatch.');
    }
    $batch=getRows($target,'SELECT id,status,mapped_records
        FROM legacy_import_batches ORDER BY id');
    if (count($batch)!==1
        || $batch[0]['status']!=='staged_needs_review') {
        throw new RuntimeException('Expected exactly one completed private staging batch.');
    }
    $batchId=(int)$batch[0]['id'];
    $tableKinds=[
        'users'=>'users','locations'=>'locations','amenities'=>'amenities',
        'companies'=>'companies','projects'=>'projects',
        'properties'=>'properties','listings'=>'listings',
        'media_assets'=>'media_assets','media_attachments'=>'media_attachments',
        'inquiries'=>'legacy_customer_inquiries',
        'leads'=>'legacy_customer_leads',
        'saved_searches'=>'saved_searches',
        'viewing_requests'=>'viewing_requests',
    ];
    $integrity=[];
    $maps=[];
    $reportCounts=[];
    foreach ($tableKinds as $table=>$destination) {
        $sourceColumns=$table==='media_attachments' ? 'id,NULL AS uuid' : 'id,uuid';
        if(in_array($table,
            ['companies','projects','properties','listings','users','locations','amenities',
             'viewing_requests','inquiries'],true)) {
            $sourceColumns.=',status';
        }
        if($table==='leads') {
            $sourceColumns.=',stage AS status';
        }
        // The legacy polymorphic attachment has no UUID.
        $src=getRows($source,'SELECT '.$sourceColumns.' FROM '.$table.' ORDER BY id');
        $new=getRows($target,
            'SELECT source_id,source_uuid,target_id,source_status
             FROM legacy_identity_maps WHERE batch_id='.$batchId.'
                AND source_table='.$target->quote($table).' ORDER BY source_id');
        $ids=getRows($target,'SELECT id FROM '.$destination.' ORDER BY id');
        $validTarget=array_fill_keys(array_map(
            static fn(array $row):int=>(int)$row['id'],$ids),true);
        $byOld=[];
        $byTarget=[];
        foreach($new as $r) {
            $oldId=(int)$r['source_id'];
            $targetId=(int)$r['target_id'];
            if(isset($byOld[$oldId]) || !isset($validTarget[$targetId])) {
                $integrity['duplicate_or_missing_mapping_'.$table]=1;
            }
            if ($table!=='media_assets' && isset($byTarget[$targetId])) {
                $integrity['noninjective_mapping_'.$table]=1;
            }
            $byOld[$oldId]=$r;
            $byTarget[$targetId]=true;
        }
        foreach($src as $row) {
            $old=(int)$row['id'];
            if (!isset($byOld[$old])) {
                $integrity['missing_source_identity_'.$table]=1;
                continue;
            }
            $sourceUuid=$row['uuid']??null;
            $mappedUuid=$byOld[$old]['source_uuid'];
            if ($sourceUuid!==$mappedUuid) {
                $integrity['legacy_uuid_mismatch_'.$table]=1;
            }
            if (isset($row['status'])
                && $row['status']!==$byOld[$old]['source_status']) {
                $integrity['source_moderation_status_mismatch_'.$table]=1;
            }
        }
        if(count($src)!==count($new)
            || count($validTarget)!==count($byTarget)) {
            $integrity['mapping_row_count_mismatch_'.$table]=1;
        }
        $maps[$table]=array_map('intval',array_column($new,'target_id','source_id'));
        $reportCounts[$table]=[
            'source'=>count($src),'identity_mappings'=>count($new),
            'unique_destination_records'=>count($byTarget),
        ];
    }

    // Historical inquiry/lead ownership and source status must survive
    // without becoming active customer communications or CRM outreach.
    foreach ([
        'inquiries'=>['legacy_customer_inquiries','status','source_status'],
        'leads'=>['legacy_customer_leads','stage','source_stage'],
    ] as $kind=>[$dest,$statusColumn,$destStatusColumn]) {
        $sourceRecords=getRows($source,'SELECT id,company_id,'.$statusColumn
            .' FROM '.$kind.' ORDER BY id');
        $archivedRecords=getRows($target,'SELECT id,company_id,'.$destStatusColumn
            .',review_status FROM '.$dest.' ORDER BY id');
        $byId=[];
        foreach ($archivedRecords as $r) {
            $byId[(int)$r['id']]=$r;
        }
        foreach ($sourceRecords as $original) {
            $targetId=$maps[$kind][(int)$original['id']]??null;
            $copy=$targetId===null?null:($byId[$targetId]??null);
            if ($copy===null
                || (string)$copy[$destStatusColumn] !== (string)$original[$statusColumn]
                || $copy['review_status']!=='needs_review'
                || ($original['company_id']===null
                    ? $copy['company_id']!==null
                    : (int)$copy['company_id']!==($maps['companies'][(int)$original['company_id']]??-1))) {
                $integrity['archived_'.$kind.'_relationship_or_privacy_mismatch']=1;
            }
        }
    }

    // Compare every nullable legacy relationship to its precise mapped
    // Laravel destination, rather than comparing only company ownership.
    foreach ([
        'inquiries'=>[
            'table'=>'legacy_customer_inquiries',
            'relations'=>[
                'company_id'=>'companies','project_id'=>'projects',
                'property_id'=>'properties','listing_id'=>'listings',
                'user_id'=>'users','assigned_user_id'=>'users',
            ],
        ],
        'leads'=>[
            'table'=>'legacy_customer_leads',
            'relations'=>[
                'company_id'=>'companies','inquiry_id'=>'inquiries',
                'assigned_user_id'=>'users','preferred_location_id'=>'locations',
            ],
        ],
    ] as $kind=>$spec) {
        $fields=array_keys($spec['relations']);
        $cols='id,uuid,'.implode(',',$fields);
        $srcRecords=getRows($source,'SELECT '.$cols.' FROM '.$kind.' ORDER BY id');
        $targetRecords=getRows($target,'SELECT id,source_uuid,'.implode(',',$fields)
            .' FROM '.$spec['table'].' ORDER BY id');
        $targetById=[];
        foreach ($targetRecords as $row) {
            $targetById[(int)$row['id']]=$row;
        }
        foreach ($srcRecords as $old) {
            $targetId=$maps[$kind][(int)$old['id']]??null;
            $new=$targetId===null?null:($targetById[$targetId]??null);
            if ($new===null || $new['source_uuid']!==$old['uuid']) {
                $integrity['archived_'.$kind.'_uuid_or_identity_mismatch']=1;
                continue;
            }
            foreach ($spec['relations'] as $foreign=>$parentTable) {
                $mapped=$old[$foreign]===null?null:
                    ($maps[$parentTable][(int)$old[$foreign]]??null);
                $actual=$new[$foreign]===null?null:(int)$new[$foreign];
                if ($mapped!==$actual) {
                    $integrity['archived_'.$kind.'_'.$foreign.'_mismatch']=1;
                }
            }
        }
    }

    // Relationship reconciliation cannot trust only row counts. Check exact
    // old-to-new pairs and ownership for each imported saved listing.
    $userMap=$maps['users'];
    $listingMap=$maps['listings'];
    $sourceSaved=getRows($source,
        'SELECT user_id,listing_id FROM saved_listings ORDER BY user_id,listing_id');
    $targetSaved=getRows($target,
        'SELECT user_id,listing_id FROM saved_listings ORDER BY user_id,listing_id');
    $savedMap=[];
    foreach($targetSaved as $row) {
        $savedMap[(int)$row['user_id'].':'.(int)$row['listing_id']]=true;
    }
    foreach($sourceSaved as $row) {
        $user=$userMap[(int)$row['user_id']]??null;
        $listing=$listingMap[(int)$row['listing_id']]??null;
        if($user===null || $listing===null || !isset($savedMap[$user.':'.$listing])) {
            $integrity['saved_listing_relationship_mismatch']=1;
        }
    }
    if(count($sourceSaved)!==count($targetSaved)) {
        $integrity['saved_listing_count_mismatch']=1;
    }
    $reportCounts['saved_listings']=[
        'source'=>count($sourceSaved),'target'=>count($targetSaved),
    ];

    foreach (['comparison_items','recent_listing_views'] as $activity) {
        $oldActivity=getRows($source,'SELECT user_id,listing_id FROM '.$activity.' ORDER BY user_id,listing_id');
        $newActivity=getRows($target,'SELECT user_id,listing_id FROM '.$activity.' ORDER BY user_id,listing_id');
        $pairs=[];
        foreach($newActivity as $row) {
            $key=(int)$row['user_id'].':'.(int)$row['listing_id'];
            if(isset($pairs[$key])) {
                $integrity['duplicate_'.$activity]=1;
            }
            $pairs[$key]=true;
        }
        foreach($oldActivity as $row) {
            $user=$userMap[(int)$row['user_id']]??null;
            $listing=$listingMap[(int)$row['listing_id']]??null;
            if($user===null || $listing===null || !isset($pairs[$user.':'.$listing])) {
                $integrity['legacy_relationship_mismatch_'.$activity]=1;
            }
        }
        if(count($oldActivity)!==count($newActivity)) {
            $integrity['count_mismatch_'.$activity]=1;
        }
        $reportCounts[$activity]=[
            'source'=>count($oldActivity),'target'=>count($newActivity),
        ];
    }

    // Sample-by-identity checks preserve source date windows and alert opt-in
    // as private evidence; imported notifications and historical appointments
    // never become active merely because the data exists.
    foreach (getRows($source,'SELECT id,location_id,alert_frequency,alerts_enabled,
           bedrooms_min FROM saved_searches ORDER BY id') as $record) {
        $targetId=$maps['saved_searches'][(int)$record['id']]??null;
        $row=$targetId===null?null:getRows($target,
            'SELECT legacy_filter_evidence,legacy_alert_frequency,
                legacy_alerts_enabled,legacy_review_required
             FROM saved_searches WHERE id='.(int)$targetId);
        if($row===null || count($row)!==1) {
            $integrity['saved_search_evidence_missing']=1;
            continue;
        }
        $evidence=json_decode((string)$row[0]['legacy_filter_evidence'],true);
        $mappedLocation=$record['location_id']===null?null:($maps['locations'][(int)$record['location_id']]??null);
        if(!is_array($evidence) || ($evidence['location_id']??null)!==$mappedLocation
            || (string)($evidence['bedrooms_min']??'')!==(string)($record['bedrooms_min']??'')
            || $row[0]['legacy_alert_frequency']!==$record['alert_frequency']
            || (int)$row[0]['legacy_alerts_enabled']!==0
            || (int)$row[0]['legacy_review_required']!==1) {
            $integrity['saved_search_filter_or_alert_evidence_mismatch']=1;
        }
    }
    foreach (getRows($source,'SELECT id,preferred_date,preferred_time_window,
            status,company_id FROM viewing_requests ORDER BY id') as $record) {
        $targetId=$maps['viewing_requests'][(int)$record['id']]??null;
        $row=$targetId===null?null:getRows($target,
            'SELECT legacy_preferred_date,legacy_preferred_window,
                legacy_original_status,legacy_company_id,status
             FROM viewing_requests WHERE id='.(int)$targetId);
        if($row===null || count($row)!==1
            || $row[0]['legacy_preferred_date']!==$record['preferred_date']
            || $row[0]['legacy_preferred_window']!==$record['preferred_time_window']
            || $row[0]['legacy_original_status']!==$record['status']
            || (int)$row[0]['legacy_company_id']!==($maps['companies'][(int)$record['company_id']]??-1)
            || $row[0]['status']!=='legacy_review') {
            $integrity['historical_viewing_appointment_mismatch']=1;
        }
    }

    $review=getRows($target, 'SELECT source_company_id,source_user_id,
         source_role,source_status,target_membership_id,decision
         FROM legacy_company_membership_reviews WHERE batch_id='.$batchId);
    $sourceMembers=getRows($source,
        'SELECT company_id,user_id,member_role,status FROM company_members');
    $targetMembers=getRows($target,'SELECT id,company_id,user_id,role,status FROM company_user');
    $memberIndex=[];
    foreach($targetMembers as $row) {
        $memberIndex[(int)$row['id']]=$row;
    }
    $reviewIndex=[];
    foreach($review as $row) {
        $reviewIndex[$row['source_company_id'].':'.$row['source_user_id']]=$row;
    }
    foreach($sourceMembers as $row) {
        $key=$row['company_id'].':'.$row['user_id'];
        $record=$reviewIndex[$key]??null;
        $member=$record?($memberIndex[(int)$record['target_membership_id']]??null):null;
        if ($record===null || $member===null
            || $record['source_role']!==$row['member_role']
            || $record['source_status']!==$row['status']
            || $record['decision']!=='needs_independent_approval'
            || (int)$member['company_id']!==($maps['companies'][(int)$row['company_id']]??-1)
            || (int)$member['user_id']!==($userMap[(int)$row['user_id']]??-1)
            || $member['role']!=='member'
            || $member['status']!=='inactive') {
            $integrity['company_membership_or_role_escalation']=1;
        }
    }
    if(count($sourceMembers)!==count($review)
        || count($targetMembers)!==count($review)) {
        $integrity['company_membership_review_count_mismatch']=1;
    }
    $reportCounts['company_members']=[
        'source'=>count($sourceMembers),'inactive_target'=>count($targetMembers),
        'independent_role_reviews'=>count($review),
    ];

    $unsafeChecks=[
        'privileged_or_verified_imported_users'=>"SELECT COUNT(*) FROM users
            WHERE system_role<>'user' OR email_verified_at IS NOT NULL",
        'published_imported_companies'=>"SELECT COUNT(*) FROM companies
            WHERE status<>'draft' OR published_at IS NOT NULL",
        'published_imported_projects'=>"SELECT COUNT(*) FROM projects
            WHERE status<>'draft' OR published_at IS NOT NULL",
        'published_imported_properties'=>"SELECT COUNT(*) FROM properties WHERE status<>'draft'",
        'published_imported_listings'=>"SELECT COUNT(*) FROM listings
            WHERE status<>'draft' OR published_at IS NOT NULL",
        'imported_old_viewings_are_review_only'=>"SELECT COUNT(*) FROM viewing_requests
            WHERE legacy_uuid IS NOT NULL AND (status<>'legacy_review' OR legacy_original_status IS NULL)",
        'archived_customer_leads_are_private'=>"SELECT COUNT(*) FROM legacy_customer_leads
            WHERE review_status<>'needs_review' OR reviewed_at IS NOT NULL",
        'archived_customer_inquiries_are_private'=>"SELECT COUNT(*) FROM legacy_customer_inquiries
            WHERE review_status<>'needs_review' OR reviewed_at IS NOT NULL",
        'imported_search_alerts_disabled'=>"SELECT COUNT(*) FROM saved_searches
            WHERE legacy_uuid IS NOT NULL AND (legacy_alerts_enabled<>0 OR legacy_review_required<>1)",
        'inappropriate_media_rights'=>"SELECT COUNT(*) FROM media_assets
            WHERE usage_rights<>'unreviewed' OR path NOT LIKE 'private/media/%'",
    ];
    foreach($unsafeChecks as $name=>$sql) {
        if(oneCount($target,$sql)!==0) {
            $integrity[$name]=1;
        }
    }

    $sourceTables=$source->query("SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $covered=[...array_keys($tableKinds),
        'saved_listings','comparison_items','recent_listing_views',
        'company_members','property_amenities','project_amenities',
        'location_countries','location_data_packs','location_hierarchy_registry'];
    $unconverted=[];
    foreach($sourceTables as $table) {
        if(in_array($table,$covered,true)) {
            continue;
        }
        if(preg_match('/^[a-z][a-z0-9_]*$/D',$table)!==1) {
            throw new RuntimeException('Unsupported source schema identifier.');
        }
        $count=oneCount($source,'SELECT COUNT(*) FROM '.$table);
        if($count>0) {
            $unconverted[$table]=$count;
        }
    }
    ksort($unconverted);
    echo json_encode([
        'audit'=>'barama-post-stage-reconciliation-v1',
        'mode'=>'READ_ONLY',
        'source'=>'isolated_legacy_copy','target'=>'isolated_laravel_copy',
        'mapped_core_counts'=>$reportCounts,
        'unconverted_nonempty_source_tables'=>$unconverted,
        'integrity_blockers'=>array_keys($integrity),
        'integrity_status'=>$integrity===[]?'MAPPED_CORE_VERIFIED':'FAILED',
        'production_release_status'=>'BLOCKED_REQUIRES_ALL_CUSTOMER_MODULES_AND_HOST_ACCEPTANCE',
        'personal_data_printed'=>false,'databases_modified'=>false,
    ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
    exit($integrity===[]?0:2);
} catch(Throwable $error) {
    fwrite(STDERR,"RECONCILE_FAILED=PRIVATE_DATA_OR_SCHEMA_VERIFICATION\n");
    exit(4);
}
