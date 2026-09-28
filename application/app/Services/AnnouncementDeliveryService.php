<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\AnnouncementRepository;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class AnnouncementDeliveryService
{
    /** @return array{processed:int,published:int,recipients:int} */
    public function processDue(int $limit=100): array
    {
        $repository=new AnnouncementRepository();
        $result=['processed'=>0,'published'=>0,'recipients'=>0];

        foreach($repository->dueIds($limit) as $id){
            $published=$this->publish($id,null,true);
            $result['processed']++;
            if(($published['status']??'')==='published'){
                $result['published']++;
                $result['recipients']+=(int)$published['recipients'];
            }
        }
        return $result;
    }

    /** @return array{announcement_id:int,status:string,recipients:int,in_app:int,email:int,skipped:int} */
    public function publish(int $id,?int $actorUserId=null,bool $scheduledOnly=false): array
    {
        if($id<1){throw new InvalidArgumentException('A valid announcement ID is required.');}
        $pdo=Database::connection();
        $repository=new AnnouncementRepository();
        $result=['announcement_id'=>$id,'status'=>'skipped','recipients'=>0,'in_app'=>0,'email'=>0,'skipped'=>0];

        try{
            $pdo->beginTransaction();
            $announcement=$repository->find($id,true);
            if($announcement===null){throw new InvalidArgumentException('Announcement was not found.');}
            $status=(string)$announcement['status'];

            if($status==='published'){
                $pdo->commit();
                $result['status']='published';
                return $result;
            }
            if($scheduledOnly&&$status!=='scheduled'){
                $pdo->commit();
                return $result;
            }
            if(!in_array($status,['draft','scheduled'],true)){
                throw new DomainException('Only draft or scheduled announcements can be published.');
            }
            if($scheduledOnly&&(empty($announcement['scheduled_at'])||strtotime((string)$announcement['scheduled_at'])>time())){
                $pdo->commit();
                return $result;
            }

            $recipients=$repository->recipients((string)$announcement['audience']);
            $notificationService=new NotificationService();

            foreach($recipients as $recipient){
                $delivery=$notificationService->notifyUser(
                    (int)$recipient['id'],
                    'announcements',
                    [
                        'type'=>'announcement_published',
                        'title'=>(string)$announcement['title'],
                        'message'=>(string)$announcement['message'],
                        'action_url'=>$announcement['cta_url']!==null?(string)$announcement['cta_url']:null,
                        'action_label'=>$announcement['cta_label']!==null?(string)$announcement['cta_label']:'Open announcement',
                        'entity_type'=>'announcement',
                        'entity_id'=>$id,
                        'dedupe_key'=>'announcement:'.$id,
                        'email_subject'=>(string)$announcement['title'],
                        'in_app'=>(bool)$announcement['send_in_app'],
                        'email'=>(bool)$announcement['send_email'],
                    ]
                );

                if(!$repository->recordDelivery($id,(int)$recipient['id'],$delivery['notification_id'],$delivery['email_outbox_id'])){
                    continue;
                }

                $result['recipients']++;
                if($delivery['notification_id']!==null){$result['in_app']++;}
                if($delivery['email_outbox_id']!==null){$result['email']++;}
                if($delivery['notification_id']===null&&$delivery['email_outbox_id']===null){$result['skipped']++;}
            }

            $repository->markPublished($id,$actorUserId);
            $pdo->commit();
            $result['status']='published';
        }catch(Throwable $exception){
            if($pdo->inTransaction()){$pdo->rollBack();}
            throw $exception;
        }

        (new SecurityAuditService())->record(
            $actorUserId!==null?'admin.communication_announcement_published':'system.communication_announcement_published',
            $actorUserId,
            null,
            [
                'announcement_id'=>$id,'audience'=>(string)$announcement['audience'],
                'recipient_count'=>$result['recipients'],'in_app_count'=>$result['in_app'],
                'email_count'=>$result['email'],'skipped_count'=>$result['skipped'],
            ]
        );

        return $result;
    }
}
