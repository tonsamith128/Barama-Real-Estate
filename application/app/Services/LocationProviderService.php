<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\LocationProviderRepository;
use Barama\Repositories\GlobalLocationRepository;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

final class LocationProviderService
{
    public function viewModel(): array
    {
        $this->assertAdmin();
        $repo = new LocationProviderRepository();
        $config = $repo->get();
        return [
            'provider' => $repo->activeProvider(),
            'has_token' => $config['token'] !== '',
            'tested' => $repo->tested($config),
            'tested_at' => $repo->tested($config) ? (string) $config['tested_at'] : '',
            'curl_available' => function_exists('curl_init'),
        ];
    }

    public function updateToken(string $input): void
    {
        $id=$this->assertAdmin();
        $token=trim($input);
        if (strlen($token)<12 || strlen($token)>512 || preg_match('/^(pk|sk)\.[A-Za-z0-9._-]+$/D',$token)!==1) {
            throw new InvalidArgumentException('Enter a valid Mapbox access token.');
        }
        $repo=new LocationProviderRepository();
        $config=$repo->get();
        if ($token !== $config['token']) {
            $config['provider']='simple';
            $config['tested_digest']='';
            $config['tested_at']='';
            $config['token']=$token;
            $repo->save($config);
        }
        (new SecurityAuditService())->record('admin.location_provider_token_updated',$id,null,['provider'=>'mapbox']);
    }

    public function testConnection(): void
    {
        $id=$this->assertAdmin();
        $repo = new LocationProviderRepository();
        $config=$repo->get();
        if ($config['token']==='') throw new DomainException('Save a Mapbox access token first.');
        // A real API request validates connectivity, account permissions, and credentials.
        (new MapboxLocationClient())->suggestions($config['token'],'Phnom Penh');
        $config['tested_digest']=hash('sha256',$config['token']);
        $config['tested_at']=gmdate('c');
        $repo->save($config);
        (new SecurityAuditService())->record('admin.location_provider_connection_tested',$id,null,['provider'=>'mapbox','result'=>'pass']);
    }

    public function activate(string $provider): void
    {
        $id=$this->assertAdmin();
        $repo=new LocationProviderRepository();
        $config=$repo->get();
        if (!in_array($provider,['simple','mapbox'],true)) throw new InvalidArgumentException('Choose a supported location provider.');
        if ($provider==='mapbox' && !$repo->tested($config)) {
            throw new DomainException('Save and successfully test Mapbox before activation.');
        }
        $config['provider']=$provider;
        $repo->save($config);
        (new SecurityAuditService())->record('admin.location_provider_activated',$id,null,['provider'=>$provider]);
    }

    /** @return array<int,array<string,mixed>> */
    public function search(string $q, string $country, int $userId): array
    {
        $repo=new LocationProviderRepository();
        if ($repo->activeProvider()!=='mapbox') throw new DomainException('Mapbox Location is not enabled.');
        $config=$repo->get();
        $country=strtoupper(trim($country));
        if ($country!=='' ) {
            if (preg_match('/^[A-Z]{2}$/',$country)!==1) throw new InvalidArgumentException('Invalid country.');
            $countries = new GlobalLocationRepository();
            $record=$countries->country($country);
            if ($record===null || ($countries->mode()==='selected' && (int)$record['enabled']!==1)) {
                throw new DomainException('The selected country is unavailable.');
            }
        }
        $this->rateLimit($userId);
        return (new MapboxLocationClient())->suggestions($config['token'],$q,$country);
    }

    private function rateLimit(int $userId): void
    {
        $directory=APP_PATH.'/storage/app/location-provider';
        if (is_link($directory) || (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory))) {
            throw new RuntimeException('Location request limiter is unavailable.');
        }
        $file=$directory.'/request-limits.json';
        if (is_link($file)) throw new RuntimeException('Location request limiter is unsafe.');
        $handle=fopen($file,'c+');
        if ($handle===false) throw new RuntimeException('Location request limiter is unavailable.');
        try {
            @chmod($file,0600);
            if (!flock($handle, LOCK_EX)) throw new RuntimeException('Location request limiter is unavailable.');
            $raw=stream_get_contents($handle);
            $data=is_string($raw)?json_decode($raw,true):null;
            if (!is_array($data)) $data=[];
            $minute=(int)floor(time()/60);
            $key=(string)$userId;
            $data=array_filter($data, static fn($row)=>is_array($row) && ($row['minute']??0)===$minute);
            $entry=$data[$key]??['minute'=>$minute,'count'=>0];
            if ((int)$entry['count']>=8) throw new DomainException('Too many Mapbox requests. Try again in a minute.');
            $total=0;
            foreach ($data as $row) $total+=(int)($row['count']??0);
            if ($total>=100) throw new DomainException('Mapbox request budget temporarily reached.');
            $entry['count']++;
            $data[$key]=$entry;
            rewind($handle); ftruncate($handle,0);
            fwrite($handle,json_encode($data,JSON_THROW_ON_ERROR));fflush($handle);
        } finally {flock($handle, LOCK_UN); fclose($handle);}
    }

    private function assertAdmin(): int
    {
        $auth=new AuthService();
        if (!$auth->check() || !$auth->hasRole('administrator') || !$auth->can('location.manage')) {
            throw new DomainException('Administrator location-management permission is required.');
        }
        return (int)(auth_user()['id']??0);
    }
}
