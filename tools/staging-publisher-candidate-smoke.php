<?php

declare(strict_types=1);

namespace Barama\Services\Updates {
    final class SignedReleaseInspector
    {
        public static array $report = [];
        public static bool $reject = false;
        public static int $calls = 0;

        public static function inspect(string $installedVersion): array
        {
            self::$calls++;
            if (self::$reject) throw new \RuntimeException('SIGNED_ZIP_INVALID');
            return self::$report;
        }
    }
}
namespace {
    require dirname(__DIR__) . '/application/app/Services/Updates/StagingPublisherCandidateV1.php';
    use Barama\Services\Updates\SignedReleaseInspector;
    use Barama\Services\Updates\StagingPublisherCandidateV1;

    define('BASE_PATH', '/www/wwwroot/baramastaging.tonsamith.com');
    define('APP_PATH', BASE_PATH . '/application');
    $counter=0;
    $check=static function(bool $ok,string $label)use(&$counter):void {
        if(!$ok) throw new \RuntimeException('CANDIDATE_TEST_FAILED:'.$label);
        ++$counter;
    };
    $sha=str_repeat('a',64);
    $feed=[
        'status'=>'available',
        'entitlement'=>'Eligible',
        'feed'=>'Signed release verified',
        'version'=>'0.9.23',
        'archive_sha256'=>$sha,
    ];
    SignedReleaseInspector::$report=[
        'status'=>'Signed package verified (not installed)',
        'version'=>'0.9.23',
        'archive_sha256'=>$sha,
        'files'=>2,
    ];
    $ok=StagingPublisherCandidateV1::inspect(BASE_PATH,'0.9.22-dev',$feed);
    $check($ok['status']==='PUBLISHER_RELEASE_AND_STAGED_ZIP_MATCH_READ_ONLY','Match');
    $check($ok['publisher_release_matched_to_staged_zip']===true,'Matches digest');
    $check($ok['publisher_entitlement_confirmed_for_discovery']===true,'Entitlement');
    $check($ok['signed_zip_verified']===true,'Local signature inspected');
    $check($ok['ready_to_install']===false && $ok['auto_install_enabled']===false,'Installer blocked');
    $check($ok['database_connections']===false && $ok['database_writes']===false
        && $ok['website_writes']===false,'No data touched');
    $check(in_array('FULL_DATABASE_RECOVERY_NOT_PROVEN',$ok['blockers'],true)
        && in_array('LIVE_UPDATE_ROLLBACK_NOT_PROVEN',$ok['blockers'],true),'Recovery barriers');
    $before=SignedReleaseInspector::$calls;
    $prod=StagingPublisherCandidateV1::inspect('/www/wwwroot/baramarealestate.tonsamith.com','0.9.22-dev',$feed);
    $check($prod['status']==='STAGING_ONLY' && SignedReleaseInspector::$calls===$before,'Production refused');
    foreach([
        ['status'=>'ineligible'],
        ['entitlement'=>'Not entitled'],
        ['feed'=>'No release published'],
        ['archive_sha256'=>'not-a-hash'],
        ['version'=>'0.9.22-dev'],
    ] as $patch){
        $r=StagingPublisherCandidateV1::inspect(BASE_PATH,'0.9.22-dev',array_replace($feed,$patch));
        $check($r['status']==='PUBLISHER_CANDIDATE_NOT_CONFIRMED' && !$r['signed_zip_verified'],'Unconfirmed publisher');
    }
    $old=SignedReleaseInspector::$report;
    SignedReleaseInspector::$report['archive_sha256']=str_repeat('b',64);
    $mismatch=StagingPublisherCandidateV1::inspect(BASE_PATH,'0.9.22-dev',$feed);
    $check($mismatch['status']==='SIGNED_ZIP_DOES_NOT_MATCH_PUBLISHER_RELEASE'
        && !$mismatch['publisher_release_matched_to_staged_zip'],'Different archive blocked');
    SignedReleaseInspector::$report=$old;
    SignedReleaseInspector::$report['version']='0.9.24';
    $r=StagingPublisherCandidateV1::inspect(BASE_PATH,'0.9.22-dev',$feed);
    $check($r['status']==='SIGNED_ZIP_DOES_NOT_MATCH_PUBLISHER_RELEASE','Different version blocked');
    SignedReleaseInspector::$report=$old;
    SignedReleaseInspector::$reject=true;
    $bad=StagingPublisherCandidateV1::inspect(BASE_PATH,'0.9.22-dev',$feed);
    $check($bad['status']==='SIGNED_ZIP_MISSING_OR_REJECTED'
        && !$bad['signed_zip_verified'],'Invalid local archive blocked');

    echo "SIGNED_PUBLISHER_ARCHIVE_AND_VERSION_BINDING=PASS\n";
    echo "UNMATCHED_AND_DENIED_ENTITLEMENTS=REJECTED\n";
    echo "PRODUCTION_CANDIDATE_GATE=BLOCKED\n";
    echo "LIVE_WEBSITE_WRITES=NO\n";
    echo "DATABASE_CONNECTIONS=NO\n";
    echo "AUTO_INSTALL=DISABLED\n";
    echo 'TEST_ASSERTIONS='.$counter."\n";
}
