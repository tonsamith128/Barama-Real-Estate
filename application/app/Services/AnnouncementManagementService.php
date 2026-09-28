<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\AnnouncementRepository;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class AnnouncementManagementService
{
    /** @return array<string,string> */
    public static function audienceOptions(): array
    {
        return [
            'all'=>'All active users',
            'customer'=>'Customers',
            'developer'=>'Developers',
            'administrator'=>'Administrators',
        ];
    }

    /** @return array<string,string> */
    public static function priorityOptions(): array
    {
        return ['normal'=>'Normal','high'=>'High','urgent'=>'Urgent'];
    }

    /** @param array<string,mixed> $input */
    public function create(array $input): int
    {
        $actor=$this->administrator();
        $data=$this->validated($input);
        $data['actor_user_id']=(int)$actor['id'];
        $id=(new AnnouncementRepository())->create($data);

        (new SecurityAuditService())->record(
            'admin.communication_announcement_created',(int)$actor['id'],null,
            ['announcement_id'=>$id,'audience'=>$data['audience'],'priority'=>$data['priority']]
        );
        return $id;
    }

    /** @param array<string,mixed> $input */
    public function update(int $id,array $input): void
    {
        $actor=$this->administrator();
        $repository=new AnnouncementRepository();
        $announcement=$this->announcement($repository,$id);
        $status=(string)$announcement['status'];

        if(!in_array($status,['draft','scheduled'],true)){
            throw new DomainException('Only draft or scheduled announcements can be edited.');
        }

        $data=$this->validated($input);
        if($status==='scheduled'&&($data['scheduled_at']===null||!$this->isFuture($data['scheduled_at']))){
            throw new InvalidArgumentException('A scheduled announcement requires a future publish time.');
        }

        $data['actor_user_id']=(int)$actor['id'];
        $repository->update($id,$data);
        (new SecurityAuditService())->record(
            'admin.communication_announcement_updated',(int)$actor['id'],null,
            ['announcement_id'=>$id,'status'=>$status,'audience'=>$data['audience'],'priority'=>$data['priority']]
        );
    }

    /** @param array<string,mixed> $input */
    public function setStatus(int $id,string $status,array $input=[]): void
    {
        $actor=$this->administrator();
        $repository=new AnnouncementRepository();
        $pdo=Database::connection();
        $status=strtolower(trim($status));

        try{
            $pdo->beginTransaction();
            $announcement=$repository->find($id,true);
            if($announcement===null){throw new InvalidArgumentException('Announcement was not found.');}
            $current=(string)$announcement['status'];

            if($status==='scheduled'){
                if($current!=='draft'){throw new DomainException('Only a draft announcement can be scheduled.');}
                $scheduledAt=$this->normalizeDateTime((string)($input['scheduled_at']??''));
                if($scheduledAt===null||!$this->isFuture($scheduledAt)){
                    throw new InvalidArgumentException('Choose a future publish time before scheduling.');
                }
                $repository->schedule($id,$scheduledAt,(int)$actor['id']);
            }elseif($status==='draft'){
                if($current!=='scheduled'){throw new DomainException('Only a scheduled announcement can return to draft.');}
                $repository->returnToDraft($id,(int)$actor['id']);
            }elseif($status==='cancelled'){
                if(!in_array($current,['draft','scheduled'],true)){throw new DomainException('Only draft or scheduled announcements can be cancelled.');}
                $repository->cancel($id,(int)$actor['id']);
            }elseif($status==='archived'){
                if(!in_array($current,['published','cancelled'],true)){throw new DomainException('Only published or cancelled announcements can be archived.');}
                $repository->archive($id,(int)$actor['id']);
            }else{
                throw new InvalidArgumentException('Unsupported announcement status transition.');
            }

            $pdo->commit();
        }catch(Throwable $exception){
            if($pdo->inTransaction()){$pdo->rollBack();}
            throw $exception;
        }

        (new SecurityAuditService())->record(
            'admin.communication_announcement_status_changed',(int)$actor['id'],null,
            ['announcement_id'=>$id,'from_status'=>$current,'to_status'=>$status]
        );
    }

    /** @return array<string,mixed> */
    public function publishNow(int $id): array
    {
        $actor=$this->administrator();
        return (new AnnouncementDeliveryService())->publish($id,(int)$actor['id'],false);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function validated(array $input): array
    {
        $title=trim((string)($input['title']??''));
        $message=trim((string)($input['message']??''));
        $ctaLabel=trim((string)($input['cta_label']??''));
        $ctaUrl=trim((string)($input['cta_url']??''));
        $audience=strtolower(trim((string)($input['audience']??'all')));
        $priority=strtolower(trim((string)($input['priority']??'normal')));
        $scheduledAt=$this->normalizeDateTime((string)($input['scheduled_at']??''));
        $sendInApp=$this->enabled($input,'send_in_app');
        $sendEmail=$this->enabled($input,'send_email');

        if(mb_strlen($title)<3||mb_strlen($title)>191){throw new InvalidArgumentException('Announcement title must contain 3 to 191 characters.');}
        if(mb_strlen($message)<3||mb_strlen($message)>4000){throw new InvalidArgumentException('Announcement message must contain 3 to 4000 characters.');}
        if(mb_strlen($ctaLabel)>80){throw new InvalidArgumentException('CTA label cannot exceed 80 characters.');}
        if(mb_strlen($ctaUrl)>500){throw new InvalidArgumentException('CTA URL cannot exceed 500 characters.');}
        if(($ctaLabel==='')!==($ctaUrl==='')){throw new InvalidArgumentException('CTA label and CTA URL must be provided together.');}
        if($ctaUrl!==''&&(!str_starts_with($ctaUrl,'/')||str_starts_with($ctaUrl,'//')||preg_match('/[\x00-\x20]/',$ctaUrl)===1)){
            throw new InvalidArgumentException('CTA URL must be a safe internal path beginning with /.');
        }
        if(!array_key_exists($audience,self::audienceOptions())){throw new InvalidArgumentException('Choose a supported announcement audience.');}
        if(!array_key_exists($priority,self::priorityOptions())){throw new InvalidArgumentException('Choose a supported announcement priority.');}
        if(!$sendInApp&&!$sendEmail){throw new InvalidArgumentException('Enable at least one delivery channel.');}

        return [
            'title'=>$title,'message'=>$message,'cta_label'=>$ctaLabel!==''?$ctaLabel:null,'cta_url'=>$ctaUrl!==''?$ctaUrl:null,
            'audience'=>$audience,'priority'=>$priority,'send_in_app'=>$sendInApp,'send_email'=>$sendEmail,'scheduled_at'=>$scheduledAt,
        ];
    }

    /** @param array<string,mixed> $input */
    private function enabled(array $input,string $key): bool
    {
        return in_array(strtolower(trim((string)($input[$key]??''))),['1','true','on'],true);
    }

    private function normalizeDateTime(string $value): ?string
    {
        $value=trim($value);
        if($value===''){return null;}
        $date=DateTimeImmutable::createFromFormat('Y-m-d\TH:i',$value);
        if($date===false){$date=DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$value);}
        return $date!==false?$date->format('Y-m-d H:i:s'):null;
    }

    private function isFuture(string $value): bool
    {
        $timestamp=strtotime($value);
        return $timestamp!==false&&$timestamp>time();
    }

    /** @return array<string,mixed> */
    private function announcement(AnnouncementRepository $repository,int $id): array
    {
        if($id<1){throw new InvalidArgumentException('A valid announcement ID is required.');}
        $announcement=$repository->find($id);
        if($announcement===null){throw new InvalidArgumentException('Announcement was not found.');}
        return $announcement;
    }

    /** @return array<string,mixed> */
    private function administrator(): array
    {
        $auth=new AuthService();
        $user=$auth->user();
        if($user===null||!$auth->hasRole('administrator')||!$auth->can('communication.manage')){
            throw new DomainException('Administrator communications permission is required.');
        }
        return $user;
    }
}
