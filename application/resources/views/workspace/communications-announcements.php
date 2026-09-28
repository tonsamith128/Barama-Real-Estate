<?php

declare(strict_types=1);

$pageData=is_array($announcementPage??null)?$announcementPage:[];
$announcements=is_array($pageData['rows']??null)?$pageData['rows']:[];
$pagination=is_array($pageData['pagination']??null)?$pageData['pagination']:['page'=>1,'per_page'=>20,'total'=>0,'pages'=>1];
$summary=is_array($announcementSummary??null)?$announcementSummary:[];
$search=(string)($announcementSearch??'');
$filters=is_array($announcementFilters??null)?$announcementFilters:[];
$audiences=is_array($announcementAudiences??null)?$announcementAudiences:[];
$priorities=is_array($announcementPriorities??null)?$announcementPriorities:[];

$queryForPage=static function(int $page)use($search,$filters):string{
    $query=array_filter([
        'q'=>$search,'status'=>(string)($filters['status']??''),
        'audience'=>(string)($filters['audience']??''),'page'=>$page,
    ],static fn(mixed $value):bool=>$value!==''&&$value!==null);
    return url('/dashboard/announcements'.($query!==[]?'?'.http_build_query($query):''));
};
?>
<section class="workspace-page">
    <div class="workspace-page__header">
        <div>
            <span class="eyebrow"><?= e($workspaceLabel??'Admin Control Center') ?></span>
            <h1>Announcements</h1>
            <p>Create targeted platform announcements with safe scheduling, immutable published delivery snapshots, and per-recipient delivery evidence.</p>
        </div>
        <span class="workspace-context-note"><?= app_icon('bell') ?> Existing notification + mail delivery infrastructure</span>
    </div>

    <div data-workspace-notice hidden></div>

    <div class="workspace-stat-grid">
        <div class="workspace-stat"><span><?= app_icon('layers') ?></span><strong><?= e(number_format((int)($summary['total']??0))) ?></strong><small>Total</small></div>
        <div class="workspace-stat"><span><?= app_icon('edit') ?></span><strong><?= e(number_format((int)($summary['draft']??0))) ?></strong><small>Draft</small></div>
        <div class="workspace-stat"><span><?= app_icon('calendar') ?></span><strong><?= e(number_format((int)($summary['scheduled']??0))) ?></strong><small>Scheduled</small></div>
        <div class="workspace-stat"><span><?= app_icon('check') ?></span><strong><?= e(number_format((int)($summary['published']??0))) ?></strong><small>Published</small></div>
    </div>

    <section class="workspace-table-section">
        <div class="workspace-table-head"><div><h2>Create announcement</h2><p>New announcements start as drafts. Publishing snapshots current content into the canonical notification and email outbox records.</p></div></div>
        <form action="<?= e(url('/dashboard/announcements')) ?>" method="post" data-workspace-form class="workspace-form-grid">
            <?= csrf_field() ?>
            <label class="workspace-form-grid__wide"><span>Title</span><input type="text" name="title" minlength="3" maxlength="191" required placeholder="Platform update"></label>
            <label class="workspace-form-grid__wide"><span>Message</span><textarea name="message" rows="6" minlength="3" maxlength="4000" required placeholder="Write the announcement users should receive."></textarea></label>
            <label><span>Audience</span><select name="audience" required><?php foreach($audiences as $value=>$label): ?><option value="<?= e((string)$value) ?>"><?= e((string)$label) ?></option><?php endforeach; ?></select></label>
            <label><span>Priority</span><select name="priority" required><?php foreach($priorities as $value=>$label): ?><option value="<?= e((string)$value) ?>"><?= e((string)$label) ?></option><?php endforeach; ?></select></label>
            <label><span>CTA label</span><input type="text" name="cta_label" maxlength="80" placeholder="View details"></label>
            <label><span>CTA internal path</span><input type="text" name="cta_url" maxlength="500" placeholder="/properties"></label>
            <label><span>Optional planned publish time</span><input type="datetime-local" name="scheduled_at"></label>
            <div><span>Delivery channels</span><label class="workspace-check"><input type="checkbox" name="send_in_app" value="1" checked><span>In-app notification</span></label><label class="workspace-check"><input type="checkbox" name="send_email" value="1" checked><span>Email outbox</span></label></div>
            <div class="workspace-form-grid__wide"><button class="button button--primary" type="submit">Create draft</button></div>
        </form>
    </section>

    <section class="workspace-table-section">
        <div class="workspace-table-head"><div><h2>Announcement lifecycle</h2><p>Published announcements are immutable. Archive completed communications and create a new announcement for corrections.</p></div></div>

        <form method="get" class="workspace-form-grid">
            <label class="workspace-form-grid__wide"><span>Search</span><input type="search" name="q" maxlength="80" value="<?= e($search) ?>" placeholder="Search title, message, or CTA"></label>
            <label><span>Status</span><select name="status"><option value="">All statuses</option><?php foreach(['draft','scheduled','published','cancelled','archived'] as $status): ?><option value="<?= e($status) ?>" <?= (string)($filters['status']??'')===$status?'selected':'' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></label>
            <label><span>Audience</span><select name="audience"><option value="">All audiences</option><?php foreach($audiences as $value=>$label): ?><option value="<?= e((string)$value) ?>" <?= (string)($filters['audience']??'')===(string)$value?'selected':'' ?>><?= e((string)$label) ?></option><?php endforeach; ?></select></label>
            <div class="workspace-form-grid__wide"><button class="button button--primary" type="submit">Filter</button> <a class="button" href="<?= e(url('/dashboard/announcements')) ?>">Reset</a></div>
        </form>

        <div class="workspace-table-wrap"><table class="workspace-table">
            <thead><tr><th>Announcement</th><th>Audience</th><th>Status</th><th>Schedule / publish</th><th>Delivery evidence</th><th>Updated</th><th aria-label="Actions"></th></tr></thead>
            <tbody>
            <?php if($announcements===[]): ?><tr><td colspan="7"><div class="workspace-record-empty">No announcements match this view.</div></td></tr><?php endif; ?>
            <?php foreach($announcements as $announcement): ?>
                <?php
                $status=(string)$announcement['status'];
                $tone=$status==='published'?'approved':($status==='scheduled'||$status==='draft'?'pending':'neutral');
                $scheduledValue=!empty($announcement['scheduled_at'])?date('Y-m-d\TH:i',strtotime((string)$announcement['scheduled_at'])):'';
                ?>
                <tr>
                    <td><strong><?= e((string)$announcement['title']) ?></strong><small><?= e(mb_strimwidth((string)$announcement['message'],0,180,'…')) ?></small><small><?= e(ucfirst((string)$announcement['priority'])) ?> priority · <?= !empty($announcement['send_in_app'])?'In-app':'' ?><?= !empty($announcement['send_in_app'])&&!empty($announcement['send_email'])?' + ':'' ?><?= !empty($announcement['send_email'])?'Email':'' ?></small></td>
                    <td><?= e((string)($audiences[$announcement['audience']]??$announcement['audience'])) ?></td>
                    <td><span class="status-badge status-badge--<?= e($tone) ?>"><?= e(ucfirst($status)) ?></span></td>
                    <td><?php if(!empty($announcement['published_at'])): ?><strong><?= e((string)$announcement['published_at']) ?></strong><small>Published</small><?php elseif(!empty($announcement['scheduled_at'])): ?><strong><?= e((string)$announcement['scheduled_at']) ?></strong><small>Scheduled</small><?php else: ?>—<?php endif; ?></td>
                    <td><strong><?= e(number_format((int)$announcement['recipient_count'])) ?> recipients</strong><small><?= e(number_format((int)$announcement['in_app_count'])) ?> in-app · <?= e(number_format((int)$announcement['email_count'])) ?> email · <?= e(number_format((int)$announcement['skipped_count'])) ?> skipped</small></td>
                    <td><?= e((string)$announcement['updated_at']) ?><small><?= e((string)($announcement['updated_by_name']??'System')) ?></small></td>
                    <td>
                        <details class="workspace-row-actions"><summary aria-label="Announcement actions" title="Actions"><span aria-hidden="true">&#8942;</span></summary><div class="workspace-row-actions__menu">
                        <?php if(in_array($status,['draft','scheduled'],true)): ?>
                            <details><summary class="table-action">Edit announcement</summary>
                                <form action="<?= e(url('/dashboard/announcements/'.(int)$announcement['id'])) ?>" method="post" data-workspace-form class="workspace-inline-form">
                                    <?= csrf_field() ?>
                                    <input type="text" name="title" minlength="3" maxlength="191" value="<?= e((string)$announcement['title']) ?>" required>
                                    <textarea name="message" rows="6" minlength="3" maxlength="4000" required><?= e((string)$announcement['message']) ?></textarea>
                                    <select name="audience" required><?php foreach($audiences as $value=>$label): ?><option value="<?= e((string)$value) ?>" <?= (string)$announcement['audience']===(string)$value?'selected':'' ?>><?= e((string)$label) ?></option><?php endforeach; ?></select>
                                    <select name="priority" required><?php foreach($priorities as $value=>$label): ?><option value="<?= e((string)$value) ?>" <?= (string)$announcement['priority']===(string)$value?'selected':'' ?>><?= e((string)$label) ?></option><?php endforeach; ?></select>
                                    <input type="text" name="cta_label" maxlength="80" value="<?= e((string)($announcement['cta_label']??'')) ?>" placeholder="CTA label">
                                    <input type="text" name="cta_url" maxlength="500" value="<?= e((string)($announcement['cta_url']??'')) ?>" placeholder="/internal-path">
                                    <input type="datetime-local" name="scheduled_at" value="<?= e($scheduledValue) ?>">
                                    <label class="workspace-check"><input type="checkbox" name="send_in_app" value="1" <?= !empty($announcement['send_in_app'])?'checked':'' ?>><span>In-app</span></label>
                                    <label class="workspace-check"><input type="checkbox" name="send_email" value="1" <?= !empty($announcement['send_email'])?'checked':'' ?>><span>Email</span></label>
                                    <button class="table-action table-action--primary" type="submit">Save changes</button>
                                </form>
                            </details>
                            <form action="<?= e(url('/dashboard/announcements/'.(int)$announcement['id'].'/publish')) ?>" method="post" data-workspace-form><?= csrf_field() ?><button class="table-action table-action--primary" type="submit">Publish now</button></form>
                        <?php endif; ?>

                        <?php if($status==='draft'): ?>
                            <form action="<?= e(url('/dashboard/announcements/'.(int)$announcement['id'].'/status')) ?>" method="post" data-workspace-form class="workspace-inline-form"><?= csrf_field() ?><input type="hidden" name="status" value="scheduled"><input type="datetime-local" name="scheduled_at" value="<?= e($scheduledValue) ?>" required><button class="table-action" type="submit">Schedule</button></form>
                            <form action="<?= e(url('/dashboard/announcements/'.(int)$announcement['id'].'/status')) ?>" method="post" data-workspace-form><?= csrf_field() ?><input type="hidden" name="status" value="cancelled"><button class="table-action" type="submit">Cancel draft</button></form>
                        <?php elseif($status==='scheduled'): ?>
                            <form action="<?= e(url('/dashboard/announcements/'.(int)$announcement['id'].'/status')) ?>" method="post" data-workspace-form><?= csrf_field() ?><input type="hidden" name="status" value="draft"><button class="table-action" type="submit">Return to draft</button></form>
                            <form action="<?= e(url('/dashboard/announcements/'.(int)$announcement['id'].'/status')) ?>" method="post" data-workspace-form><?= csrf_field() ?><input type="hidden" name="status" value="cancelled"><button class="table-action" type="submit">Cancel schedule</button></form>
                        <?php elseif(in_array($status,['published','cancelled'],true)): ?>
                            <form action="<?= e(url('/dashboard/announcements/'.(int)$announcement['id'].'/status')) ?>" method="post" data-workspace-form><?= csrf_field() ?><input type="hidden" name="status" value="archived"><button class="table-action" type="submit">Archive</button></form>
                        <?php else: ?><span class="table-action-placeholder">Archived · immutable</span><?php endif; ?>
                        </div></details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>

        <?php if((int)$pagination['pages']>1): ?>
            <div class="workspace-table-head"><div><strong>Page <?= e((string)$pagination['page']) ?> of <?= e((string)$pagination['pages']) ?></strong><p><?= e(number_format((int)$pagination['total'])) ?> announcement<?= (int)$pagination['total']===1?'':'s' ?></p></div><div><?php if((int)$pagination['page']>1): ?><a class="button" href="<?= e($queryForPage((int)$pagination['page']-1)) ?>">Previous</a><?php endif; ?> <?php if((int)$pagination['page']<(int)$pagination['pages']): ?><a class="button" href="<?= e($queryForPage((int)$pagination['page']+1)) ?>">Next</a><?php endif; ?></div></div>
        <?php endif; ?>
    </section>
</section>
