<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class AnnouncementRepository
{
    /** @return array{rows:array<int,array<string,mixed>>,pagination:array{page:int,per_page:int,total:int,pages:int}} */
    public function paginate(string $search='', string $status='', string $audience='', int $page=1, int $perPage=20): array
    {
        $where=['CONCAT_WS(" ", a.title, a.message, COALESCE(a.cta_label, "")) LIKE :search'];
        $params=['search'=>'%'.trim($search).'%'];
        if($status!==''){$where[]='a.status = :status';$params['status']=$status;}
        if($audience!==''){$where[]='a.audience = :audience';$params['audience']=$audience;}
        $whereSql=implode(' AND ',$where);
        $pdo=Database::connection();
        $count=$pdo->prepare('SELECT COUNT(*) FROM communication_announcements a WHERE '.$whereSql);
        $count->execute($params);
        $total=(int)$count->fetchColumn();
        $perPage=max(1,min(100,$perPage));
        $pages=max(1,(int)ceil($total/$perPage));
        $page=max(1,min($page,$pages));
        $offset=($page-1)*$perPage;

        $statement=$pdo->prepare(
            'SELECT a.*,
                    cu.name AS created_by_name,
                    uu.name AS updated_by_name,
                    pu.name AS published_by_name,
                    (SELECT COUNT(*) FROM communication_announcement_deliveries d WHERE d.announcement_id=a.id) AS recipient_count,
                    (SELECT COUNT(*) FROM communication_announcement_deliveries d WHERE d.announcement_id=a.id AND d.notification_id IS NOT NULL) AS in_app_count,
                    (SELECT COUNT(*) FROM communication_announcement_deliveries d WHERE d.announcement_id=a.id AND d.email_outbox_id IS NOT NULL) AS email_count,
                    (SELECT COUNT(*) FROM communication_announcement_deliveries d WHERE d.announcement_id=a.id AND d.delivery_state="skipped") AS skipped_count
             FROM communication_announcements a
             LEFT JOIN users cu ON cu.id=a.created_by_user_id
             LEFT JOIN users uu ON uu.id=a.updated_by_user_id
             LEFT JOIN users pu ON pu.id=a.published_by_user_id
             WHERE '.$whereSql.'
             ORDER BY a.updated_at DESC,a.id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach($params as $key=>$value){$statement->bindValue(':'.$key,$value,PDO::PARAM_STR);}
        $statement->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $statement->bindValue(':offset',$offset,PDO::PARAM_INT);
        $statement->execute();

        return [
            'rows'=>$statement->fetchAll(PDO::FETCH_ASSOC),
            'pagination'=>['page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>$pages],
        ];
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        $row=Database::connection()->query(
            'SELECT COUNT(*) AS total,
                    SUM(status="draft") AS draft,
                    SUM(status="scheduled") AS scheduled,
                    SUM(status="published") AS published,
                    SUM(status="cancelled") AS cancelled,
                    SUM(status="archived") AS archived
             FROM communication_announcements'
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row)
            ? array_map(static fn(mixed $value):int=>(int)($value??0),$row)
            : ['total'=>0,'draft'=>0,'scheduled'=>0,'published'=>0,'cancelled'=>0,'archived'=>0];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id,bool $forUpdate=false): ?array
    {
        $sql='SELECT * FROM communication_announcements WHERE id=:id LIMIT 1';
        if($forUpdate){$sql.=' FOR UPDATE';}
        $statement=Database::connection()->prepare($sql);
        $statement->execute(['id'=>$id]);
        $row=$statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row)?$row:null;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $statement=Database::connection()->prepare(
            'INSERT INTO communication_announcements
                (uuid,title,message,cta_label,cta_url,audience,priority,send_in_app,send_email,status,scheduled_at,created_by_user_id,updated_by_user_id)
             VALUES
                (:uuid,:title,:message,:cta_label,:cta_url,:audience,:priority,:send_in_app,:send_email,"draft",:scheduled_at,:created_by_user_id,:updated_by_user_id)'
        );
        $statement->execute([
            'uuid'=>IdGenerator::uuid(),'title'=>$data['title'],'message'=>$data['message'],
            'cta_label'=>$data['cta_label'],'cta_url'=>$data['cta_url'],'audience'=>$data['audience'],
            'priority'=>$data['priority'],'send_in_app'=>$data['send_in_app']?1:0,'send_email'=>$data['send_email']?1:0,
            'scheduled_at'=>$data['scheduled_at'],'created_by_user_id'=>$data['actor_user_id'],'updated_by_user_id'=>$data['actor_user_id'],
        ]);
        return (int)Database::connection()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id,array $data): void
    {
        $statement=Database::connection()->prepare(
            'UPDATE communication_announcements
             SET title=:title,message=:message,cta_label=:cta_label,cta_url=:cta_url,audience=:audience,
                 priority=:priority,send_in_app=:send_in_app,send_email=:send_email,scheduled_at=:scheduled_at,
                 updated_by_user_id=:updated_by_user_id,updated_at=CURRENT_TIMESTAMP
             WHERE id=:id'
        );
        $statement->execute([
            'id'=>$id,'title'=>$data['title'],'message'=>$data['message'],'cta_label'=>$data['cta_label'],
            'cta_url'=>$data['cta_url'],'audience'=>$data['audience'],'priority'=>$data['priority'],
            'send_in_app'=>$data['send_in_app']?1:0,'send_email'=>$data['send_email']?1:0,
            'scheduled_at'=>$data['scheduled_at'],'updated_by_user_id'=>$data['actor_user_id'],
        ]);
    }

    public function schedule(int $id,string $scheduledAt,int $actorUserId): void
    {
        $statement=Database::connection()->prepare(
            'UPDATE communication_announcements
             SET status="scheduled",scheduled_at=:scheduled_at,cancelled_at=NULL,archived_at=NULL,
                 updated_by_user_id=:updated_by_user_id,updated_at=CURRENT_TIMESTAMP
             WHERE id=:id'
        );
        $statement->execute(['id'=>$id,'scheduled_at'=>$scheduledAt,'updated_by_user_id'=>$actorUserId]);
    }

    public function returnToDraft(int $id,int $actorUserId): void
    {
        $statement=Database::connection()->prepare(
            'UPDATE communication_announcements SET status="draft",scheduled_at=NULL,updated_by_user_id=:updated_by_user_id,updated_at=CURRENT_TIMESTAMP WHERE id=:id'
        );
        $statement->execute(['id'=>$id,'updated_by_user_id'=>$actorUserId]);
    }

    public function cancel(int $id,int $actorUserId): void
    {
        $statement=Database::connection()->prepare(
            'UPDATE communication_announcements SET status="cancelled",scheduled_at=NULL,cancelled_at=CURRENT_TIMESTAMP,updated_by_user_id=:updated_by_user_id,updated_at=CURRENT_TIMESTAMP WHERE id=:id'
        );
        $statement->execute(['id'=>$id,'updated_by_user_id'=>$actorUserId]);
    }

    public function archive(int $id,int $actorUserId): void
    {
        $statement=Database::connection()->prepare(
            'UPDATE communication_announcements SET status="archived",archived_at=CURRENT_TIMESTAMP,updated_by_user_id=:updated_by_user_id,updated_at=CURRENT_TIMESTAMP WHERE id=:id'
        );
        $statement->execute(['id'=>$id,'updated_by_user_id'=>$actorUserId]);
    }

    public function markPublished(int $id,?int $actorUserId): void
    {
        $statement=Database::connection()->prepare(
            'UPDATE communication_announcements
             SET status="published",scheduled_at=NULL,published_at=COALESCE(published_at,CURRENT_TIMESTAMP),
                 published_by_user_id=COALESCE(published_by_user_id,:published_by_user_id),
                 updated_by_user_id=COALESCE(:updated_by_user_id,updated_by_user_id),updated_at=CURRENT_TIMESTAMP
             WHERE id=:id'
        );
        $statement->execute(['id'=>$id,'published_by_user_id'=>$actorUserId,'updated_by_user_id'=>$actorUserId]);
    }

    /** @return array<int,int> */
    public function dueIds(int $limit=100): array
    {
        $statement=Database::connection()->prepare(
            'SELECT id FROM communication_announcements
             WHERE status="scheduled" AND scheduled_at IS NOT NULL AND scheduled_at<=CURRENT_TIMESTAMP
             ORDER BY scheduled_at ASC,id ASC LIMIT :limit'
        );
        $statement->bindValue(':limit',max(1,min(500,$limit)),PDO::PARAM_INT);
        $statement->execute();
        return array_map(static fn(mixed $value):int=>(int)$value,$statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array<int,array<string,mixed>> */
    public function recipients(string $audience): array
    {
        $pdo=Database::connection();
        if($audience==='all'){
            return $pdo->query('SELECT id,name,email FROM users WHERE status="active" ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
        }
        if($audience==='customer'){
            return $pdo->query(
                'SELECT u.id,u.name,u.email FROM users u
                 WHERE u.status="active"
                   AND NOT EXISTS (
                     SELECT 1 FROM user_roles ur
                     INNER JOIN roles r ON r.id=ur.role_id
                     WHERE ur.user_id=u.id AND r.slug IN ("developer","administrator")
                   )
                 ORDER BY u.id ASC'
            )->fetchAll(PDO::FETCH_ASSOC);
        }

        $sql='SELECT DISTINCT u.id,u.name,u.email
              FROM users u
              INNER JOIN user_roles ur ON ur.user_id=u.id
              INNER JOIN roles r ON r.id=ur.role_id
              WHERE u.status="active" AND r.slug=:role_slug';
        if($audience==='developer'){
            $sql.=' AND NOT EXISTS (
                       SELECT 1 FROM user_roles aur
                       INNER JOIN roles ar ON ar.id=aur.role_id
                       WHERE aur.user_id=u.id AND ar.slug="administrator"
                    )';
        }
        $sql.=' ORDER BY u.id ASC';
        $statement=$pdo->prepare($sql);
        $statement->execute(['role_slug'=>$audience]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recordDelivery(int $announcementId,int $userId,?int $notificationId,?int $emailOutboxId): bool
    {
        $state=$notificationId!==null||$emailOutboxId!==null?'queued':'skipped';
        $statement=Database::connection()->prepare(
            'INSERT IGNORE INTO communication_announcement_deliveries
                (announcement_id,user_id,notification_id,email_outbox_id,delivery_state)
             VALUES
                (:announcement_id,:user_id,:notification_id,:email_outbox_id,:delivery_state)'
        );
        $statement->execute([
            'announcement_id'=>$announcementId,'user_id'=>$userId,'notification_id'=>$notificationId,
            'email_outbox_id'=>$emailOutboxId,'delivery_state'=>$state,
        ]);
        return $statement->rowCount()>0;
    }
}
