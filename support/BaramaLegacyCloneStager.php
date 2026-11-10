<?php

declare(strict_types=1);

/**
 * Safe, single-transaction staging adapter for two DISPOSABLE databases.
 *
 * Only the Laravel target is written. The source connection is read-only.
 * Nobody can sign in with imported credentials: random passwords, no email
 * verification, no system role and inactive company memberships. Published
 * catalog is staged privately as drafts until a separate approval procedure.
 *
 * This is NOT a production migration or a complete business-data adapter.
 */
final class BaramaLegacyCloneStager
{
    /** @var array<string, array<int, int>> */
    private array $maps = [];
    /** @var array<string, int> */
    private array $counts = [];
    /** @var array<int, string> */
    private array $filesCreated = [];
    /** @var array<string, int> */
    private array $mediaHashes = [];
    private int $batchId = 0;

    public function __construct(
        private readonly PDO $source,
        private readonly PDO $target,
        private readonly string $sourceMediaRoot,
        private readonly string $targetMediaRoot,
    ) {
    }

    /** @return array<string, mixed> */
    public function stage(): array
    {
        $this->assertEmptyTarget();
        $this->source->exec('START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT');

        try {
            $this->target->beginTransaction();
            $this->createBatch();
            $this->copyCountries();
            $this->copyLocations();
            $this->copyAmenities();
            $this->copyUsers();
            $this->copyCompanies();
            $this->copyMemberships();
            $this->copyProjects();
            $this->copyProperties();
            $this->copyListings();
            $this->copyPivot('project_amenities','projects','amenities','project_id','amenity_id');
            $this->copyPivot('property_amenities','properties','amenities','property_id','amenity_id');
            $this->copyCountryPacks();
            $this->copyRegistry();
            $this->copyMedia();
            $this->copyMediaLinks();
            $this->copyInquiries();
            $this->verifyMappedCounts();

            $this->target->prepare('UPDATE legacy_import_batches
                SET status=?,mapped_records=?,finished_at=?,updated_at=? WHERE id=?')
                ->execute(['staged_needs_review',array_sum($this->counts),
                    date('Y-m-d H:i:s'),date('Y-m-d H:i:s'),$this->batchId]);
            $this->target->commit();
            $this->source->commit();
        } catch (Throwable $error) {
            if ($this->target->inTransaction()) {
                $this->target->rollBack();
            }
            if ($this->source->inTransaction()) {
                $this->source->rollBack();
            }
            foreach (array_reverse($this->filesCreated) as $file) {
                if (is_file($file) && !is_link($file)) {
                    @unlink($file);
                }
            }
            throw $error;
        }

        return [
            'mode'=>'DISPOSABLE_TARGET_STAGED',
            'source'=>'READ_ONLY',
            'target'=>'ISOLATED_PRIVATE_DATA',
            'mapped_record_counts'=>$this->counts,
            'identity_map_rows'=>array_sum($this->counts)-($this->counts['company_members']??0)
                -($this->counts['project_amenities']??0)-($this->counts['property_amenities']??0)
                -($this->counts['location_countries']??0)-($this->counts['location_data_packs']??0)
                -($this->counts['location_hierarchy_registry']??0),
            'media_private_files_created'=>count($this->filesCreated),
            'legacy_passwords_copied'=>false,
            'legacy_admin_roles_activated'=>false,
            'legacy_company_permissions_activated'=>false,
            'published_inventory_activated'=>false,
            'customer_email_sent'=>false,
            'release_status'=>'BLOCKED_PENDING_INDEPENDENT_ACCOUNT_MEDIA_CUSTOMER_RECONCILIATION',
        ];
    }

    private function assertEmptyTarget(): void
    {
        $tables = [
            'users','companies','company_user','locations','projects','properties',
            'listings','amenities','property_amenities','project_amenities',
            'media_assets','media_attachments','inquiries','location_countries',
            'location_data_packs','location_hierarchy_registry',
            'legacy_import_batches','legacy_identity_maps',
            'legacy_company_membership_reviews',
        ];
        foreach ($tables as $table) {
            if ((int) $this->target->query('SELECT COUNT(*) FROM '.$table)->fetchColumn() !== 0) {
                throw new RuntimeException('Target already has marketplace or legacy mapping data.');
            }
        }
        if ($this->source->query('SELECT DATABASE()')->fetchColumn()
            === $this->target->query('SELECT DATABASE()')->fetchColumn()) {
            throw new RuntimeException('Source and target must be distinct databases.');
        }
    }

    private function createBatch(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->insert('legacy_import_batches',[
            'batch_uuid'=>$this->uuid(),
            'source_schema_label'=>'isolated_legacy_clone',
            'status'=>'staging',
            'mapped_records'=>0,
            'started_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
        ]);
        $this->batchId=(int)$this->target->lastInsertId();
    }

    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6]=chr((ord($b[6])&0x0f)|0x40);
        $b[8]=chr((ord($b[8])&0x3f)|0x80);
        $h=bin2hex($b);
        return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'
            .substr($h,16,4).'-'.substr($h,20);
    }

    /** @param array<string,mixed> $row */
    private function insert(string $table, array $row): int
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/D',$table)) {
            throw new RuntimeException('Unsafe insert table.');
        }
        foreach (array_keys($row) as $column) {
            if (!preg_match('/^[a-z][a-z0-9_]*$/D',$column)) {
                throw new RuntimeException('Unsafe insert column.');
            }
        }
        $columns=implode(',',array_keys($row));
        $placeholders=implode(',',array_fill(0,count($row),'?'));
        $stmt=$this->target->prepare('INSERT INTO '.$table.' ('.$columns.') VALUES ('.$placeholders.')');
        $stmt->execute(array_values($row));
        return (int)$this->target->lastInsertId();
    }

    /** @return array<int, array<string,mixed>> */
    private function rows(string $table, string $order='id'): array
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/D',$table)
            || preg_match('/^[a-z][a-z0-9_, ]*$/D',$order)!==1) {
            throw new RuntimeException('Invalid source table ordering.');
        }
        $data=$this->source->query('SELECT * FROM '.$table.' ORDER BY '.$order)
            ->fetchAll(PDO::FETCH_ASSOC);
        if (count($data)>100000) {
            throw new RuntimeException('Source exceeds per-table staging size gate.');
        }
        $this->counts[$table]=count($data);
        return $data;
    }

    /** @param array<string,mixed> $source */
    private function map(string $table, array $source, int $targetId, ?string $status=null): void
    {
        $oldId=(int)$source['id'];
        $this->maps[$table][$oldId]=$targetId;
        $this->insert('legacy_identity_maps',[
            'batch_id'=>$this->batchId,'source_table'=>$table,
            'source_id'=>$oldId,'source_uuid'=>$source['uuid']??null,
            'target_id'=>$targetId,'source_status'=>$status,
            'reconciliation_state'=>'pending_review',
            'created_at'=>date('Y-m-d H:i:s'),
        ]);
    }

    private function mapped(string $table, mixed $old, bool $nullable=false): ?int
    {
        if ($old===null || $old==='') {
            if ($nullable) {
                return null;
            }
            throw new RuntimeException('Required legacy relationship missing.');
        }
        $id=$this->maps[$table][(int)$old]??null;
        if ($id===null) {
            throw new RuntimeException('Unmapped legacy relationship.');
        }
        return $id;
    }

    private function limited(mixed $value, int $length, bool $nullable=false): ?string
    {
        if ($value===null) {
            if ($nullable) {
                return null;
            }
            throw new RuntimeException('Required source field is NULL.');
        }
        $value=(string)$value;
        if (mb_strlen($value)>$length) {
            throw new RuntimeException('Source text exceeds exact Laravel column limit.');
        }
        return $value;
    }

    /** @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function timestamps(array $row): array
    {
        return [
            'created_at'=>$row['created_at']??date('Y-m-d H:i:s'),
            'updated_at'=>$row['updated_at']??date('Y-m-d H:i:s'),
        ];
    }

    private function copyCountries(): void
    {
        foreach ($this->rows('location_countries','country_code') as $r) {
            $this->insert('location_countries',array_merge([
                'country_code'=>$r['country_code'],'iso3'=>$r['iso3'],
                'name'=>$r['name'],'native_name'=>$r['native_name'],
                'enabled'=>$r['enabled'],'is_default'=>$r['is_default'],
                'sort_order'=>$r['sort_order'],'data_status'=>$r['data_status'],
            ],$this->timestamps($r)));
        }
    }

    private function copyLocations(): void
    {
        $pending=[];
        foreach ($this->rows('locations') as $row) {
            $pending[(int)$row['id']]=$row;
        }
        $lastCount=-1;
        while ($pending!==[] && count($pending)!==$lastCount) {
            $lastCount=count($pending);
            foreach ($pending as $oldId=>$r) {
                $parent=$r['parent_id'];
                if ($parent!==null && !isset($this->maps['locations'][(int)$parent])) {
                    continue;
                }
                $id=$this->insert('locations',array_merge([
                    'uuid'=>$r['uuid'],'name'=>$this->limited($r['name'],190),
                    'slug'=>$this->limited($r['slug'],190),
                    'type'=>$this->limited($r['type'],30),
                    'country_code'=>$r['country_code'],
                    'parent_id'=>$this->mapped('locations',$parent,true),
                    'status'=>$r['status'],'sort_order'=>$r['sort_order'],
                    'latitude'=>$r['latitude'],'longitude'=>$r['longitude'],
                ],$this->timestamps($r)));
                $this->map('locations',$r,$id,(string)$r['status']);
                unset($pending[$oldId]);
            }
        }
        if ($pending!==[]) {
            throw new RuntimeException('Orphaned or cyclic legacy locations.');
        }
    }

    private function copyAmenities(): void
    {
        foreach ($this->rows('amenities') as $r) {
            $id=$this->insert('amenities',array_merge([
                'uuid'=>$r['uuid'],'name'=>$r['name'],'slug'=>$r['slug'],
                'category'=>$r['category'],'icon_key'=>$r['icon_key'],
                'status'=>$r['status'],'sort_order'=>$r['sort_order'],
            ],$this->timestamps($r)));
            $this->map('amenities',$r,$id,(string)$r['status']);
        }
    }

    private function copyUsers(): void
    {
        foreach ($this->rows('users') as $r) {
            $id=$this->insert('users',array_merge([
                'name'=>$this->limited($r['name'],255),
                'email'=>$this->limited($r['email'],255),
                // A fresh random, unknowable password prevents taking over
                // an account with an incompatible legacy password hash.
                'password'=>password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT),
                'email_verified_at'=>null,'remember_token'=>null,
                'system_role'=>'user',
            ],$this->timestamps($r)));
            $this->map('users',$r,$id,(string)$r['status']);
        }
    }

    private function copyCompanies(): void
    {
        foreach ($this->rows('companies') as $r) {
            $id=$this->insert('companies',array_merge([
                'name'=>$this->limited($r['name'],190),
                'slug'=>$this->limited($r['slug'],190),
                'company_type'=>$this->limited($r['company_type'],40),
                'status'=>'draft','description'=>$r['description'],
                'published_at'=>null,'deleted_at'=>$r['deleted_at'],
                'location_id'=>$this->mapped('locations',$r['location_id'],true),
                'address_line'=>$r['address_line'],'email'=>$r['email'],
                'phone'=>$r['phone'],'website'=>$r['website'],
            ],$this->timestamps($r)));
            $this->map('companies',$r,$id,(string)$r['status']);
        }
    }

    private function copyMemberships(): void
    {
        foreach ($this->rows('company_members','company_id,user_id') as $r) {
            $id=$this->insert('company_user',array_merge([
                'company_id'=>$this->mapped('companies',$r['company_id']),
                'user_id'=>$this->mapped('users',$r['user_id']),
                'role'=>'member','status'=>'inactive',
            ],$this->timestamps($r)));
            $this->insert('legacy_company_membership_reviews',array_merge([
                'batch_id'=>$this->batchId,
                'source_company_id'=>$r['company_id'],
                'source_user_id'=>$r['user_id'],
                'target_membership_id'=>$id,
                'source_role'=>$this->limited($r['member_role'],40),
                'source_status'=>$this->limited($r['status'],24),
                'decision'=>'needs_independent_approval',
            ],$this->timestamps($r)));
        }
    }

    private function copyProjects(): void
    {
        foreach ($this->rows('projects') as $r) {
            $id=$this->insert('projects',array_merge([
                'company_id'=>$this->mapped('companies',$r['company_id']),
                'location_id'=>$this->mapped('locations',$r['location_id'],true),
                'name'=>$this->limited($r['name'],190),
                'slug'=>$this->limited($r['slug'],190),
                'project_type'=>$this->limited($r['project_type'],40),
                'completion_status'=>$r['completion_status'],
                'status'=>'draft','published_at'=>null,
                'description'=>$r['description'],'address_line'=>$r['address_line'],
                'latitude'=>$r['latitude'],'longitude'=>$r['longitude'],
                'launch_date'=>$r['launch_date'],
                'expected_completion_date'=>$r['expected_completion_date'],
                'completed_at'=>$r['completed_at'],'deleted_at'=>$r['deleted_at'],
            ],$this->timestamps($r)));
            $this->map('projects',$r,$id,(string)$r['status']);
        }
    }

    private function copyProperties(): void
    {
        foreach ($this->rows('properties') as $r) {
            if ($r['company_id']===null) {
                throw new RuntimeException('Legacy unassigned property requires explicit owner mapping.');
            }
            $id=$this->insert('properties',array_merge([
                'company_id'=>$this->mapped('companies',$r['company_id']),
                'project_id'=>$this->mapped('projects',$r['project_id'],true),
                'location_id'=>$this->mapped('locations',$r['location_id'],true),
                'reference_code'=>$this->limited($r['reference_code'],80),
                'title'=>$this->limited($r['title'],190),
                'slug'=>$this->limited($r['slug'],190),
                'property_type'=>$this->limited($r['property_type'],40),
                'status'=>'draft','description'=>$r['description'],
                'address_line'=>$r['address_line'],
                'bedrooms'=>$r['bedrooms'],'bathrooms'=>$r['bathrooms'],
                'parking_spaces'=>$r['parking_spaces'],
                'floor_number'=>$r['floor_number'],'total_floors'=>$r['total_floors'],
                'area_value'=>$r['area_value'],'area_unit'=>$r['area_unit'],
                'land_area_value'=>$r['land_area_value'],'land_area_unit'=>$r['land_area_unit'],
                'furnishing_status'=>$r['furnishing_status'],'year_built'=>$r['year_built'],
                'latitude'=>$r['latitude'],'longitude'=>$r['longitude'],
                'deleted_at'=>$r['deleted_at'],
            ],$this->timestamps($r)));
            $this->map('properties',$r,$id,(string)$r['status']);
        }
    }

    private function copyListings(): void
    {
        foreach ($this->rows('listings') as $r) {
            if ($r['price']===null || (float)$r['price']>99999999999999) {
                throw new RuntimeException('Legacy listing price cannot be represented losslessly.');
            }
            $id=$this->insert('listings',array_merge([
                'property_id'=>$this->mapped('properties',$r['property_id']),
                'slug'=>$this->limited($r['slug'],190),
                'transaction_type'=>$this->limited($r['transaction_type'],12),
                'status'=>'draft','published_at'=>null,
                'price'=>$r['price'],'currency'=>$r['currency'],
                'pricing_period'=>$r['pricing_period'],
                'deposit_amount'=>$r['deposit_amount'],
                'available_from'=>$r['available_from'],
                'negotiable'=>$r['negotiable'],
                'expires_at'=>$r['expires_at'],
                'deleted_at'=>$r['deleted_at'],
            ],$this->timestamps($r)));
            $this->map('listings',$r,$id,(string)$r['status']);
        }
    }

    private function copyPivot(
        string $table,string $parentTable,string $otherTable,
        string $parentField,string $otherField,
    ): void {
        foreach ($this->rows($table,$parentField.','.$otherField) as $r) {
            $this->insert($table,[
                $parentField=>$this->mapped($parentTable,$r[$parentField]),
                $otherField=>$this->mapped($otherTable,$r[$otherField]),
                'created_at'=>$r['created_at'],
            ]);
        }
    }

    private function copyCountryPacks(): void
    {
        foreach ($this->rows('location_data_packs','country_code') as $r) {
            $this->insert('location_data_packs',[
                'country_code'=>$r['country_code'],'source'=>$r['source'],
                'source_version'=>$r['source_version'],'status'=>$r['status'],
                'record_count'=>$r['record_count'],'checksum_sha256'=>$r['checksum_sha256'],
                'installed_at'=>$r['installed_at'],'updated_at'=>$r['updated_at'],
            ]);
        }
    }

    private function copyRegistry(): void
    {
        foreach ($this->rows('location_hierarchy_registry','external_code') as $r) {
            $this->insert('location_hierarchy_registry',[
                'external_code'=>$r['external_code'],'country_code'=>$r['country_code'],
                'parent_external_code'=>$r['parent_external_code'],
                'location_id'=>$this->mapped('locations',$r['location_id']),
                'source'=>$r['source'],'source_type'=>$r['source_type'],
                'created_location'=>$r['created_location'],'created_at'=>$r['created_at'],
            ]);
        }
    }

    private function checkedFile(array $r): array
    {
        $relative=str_replace('\\','/',(string)$r['path']);
        $parts=explode('/',$relative);
        if ($relative==='' || strlen($relative)>500 || str_starts_with($relative,'/')
            || count(array_intersect($parts,['','.','..']))>0) {
            throw new RuntimeException('Unsafe legacy media path.');
        }
        $path=realpath($this->sourceMediaRoot.'/'.$relative);
        if ($path===false || !str_starts_with($path,$this->sourceMediaRoot.'/')
            || !is_file($path)) {
            throw new RuntimeException('Source media missing from isolated copy.');
        }
        $cursor=$this->sourceMediaRoot;
        foreach ($parts as $part) {
            $cursor.='/'.$part;
            if (is_link($cursor)) {
                throw new RuntimeException('Legacy media symlink is forbidden.');
            }
        }
        $mime=(string)$r['mime_type'];
        $exts=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($exts[$mime]) || mime_content_type($path)!==$mime
            || filesize($path) !== (int)$r['size_bytes'] || filesize($path)>12582912) {
            throw new RuntimeException('Legacy media requires independent format/size review.');
        }
        $dimensions=@getimagesize($path);
        if (!is_array($dimensions) || (int)$dimensions[0]*(int)$dimensions[1]>24000000) {
            throw new RuntimeException('Legacy image dimensions exceed safe limits.');
        }
        $sha=hash_file('sha256',$path);
        $sourceSha=trim((string)($r['checksum_sha256']??''));
        if ($sha===false || ($sourceSha!=='' && !hash_equals(strtolower($sourceSha),$sha))) {
            throw new RuntimeException('Source media integrity mismatch.');
        }
        return [$path,$sha,$exts[$mime]];
    }

    private function copyMedia(): void
    {
        $folder=$this->targetMediaRoot.'/media';
        if (!is_dir($folder) && !mkdir($folder,0700,true)) {
            throw new RuntimeException('Cannot create private clone media folder.');
        }
        foreach ($this->rows('media_assets') as $r) {
            [$source,$sha,$extension]=$this->checkedFile($r);
            $targetId=$this->mediaHashes[$sha]??null;
            if ($targetId===null) {
                $name=$sha.'.'.$extension;
                $destination=$folder.'/'.$name;
                if (file_exists($destination) || is_link($destination)) {
                    throw new RuntimeException('Target clone media path is not empty.');
                }
                $temp=tempnam($folder,'incomplete-');
                if (!is_string($temp)) {
                    throw new RuntimeException('Cannot create private staging file.');
                }
                chmod($temp,0600);
                try {
                    if (!copy($source,$temp)
                        || !hash_equals($sha,(string)hash_file('sha256',$temp))
                        || !rename($temp,$destination)) {
                        throw new RuntimeException('Private staging image copy failed.');
                    }
                    $this->filesCreated[]=$destination;
                } finally {
                    if (is_file($temp)) {
                        @unlink($temp);
                    }
                }

                $targetId=$this->insert('media_assets',array_merge([
                    'path'=>'private/media/'.$name,
                    'original_name'=>$this->limited($r['original_name'],255),
                    'mime_type'=>$r['mime_type'],'size_bytes'=>$r['size_bytes'],
                    'width_px'=>$r['width_px'],'height_px'=>$r['height_px'],
                    'alt_text'=>$r['alt_text'],'checksum_sha256'=>$sha,
                    'usage_rights'=>'unreviewed',
                ],$this->timestamps($r)));
                $this->mediaHashes[$sha]=$targetId;
            }
            $this->map('media_assets',$r,$targetId,'private_pending_rights_review');
        }
    }

    private function copyMediaLinks(): void
    {
        foreach ($this->rows('media_attachments') as $r) {
            $type=(string)$r['entity_type'];
            if (!in_array($type,['company','project','property','listing'],true)) {
                throw new RuntimeException('Unknown legacy media attachment type.');
            }
            $typeToTable=['company'=>'companies','project'=>'projects',
                'property'=>'properties','listing'=>'listings'];
            $id=$this->insert('media_attachments',[
                'entity_type'=>$type,'entity_id'=>$this->mapped($typeToTable[$type],$r['entity_id']),
                'media_asset_id'=>$this->mapped('media_assets',$r['media_asset_id']),
                'collection'=>$this->limited($r['collection'],40),
                'position'=>$r['position'],'is_primary'=>$r['is_primary'],
                'created_at'=>$r['created_at'],'updated_at'=>$r['created_at'],
            ]);
            // Attachment IDs are individually mapped despite the original
            // being a polymorphic association instead of a foreign key.
            $this->map('media_attachments',$r,$id);
        }
    }

    private function copyInquiries(): void
    {
        foreach ($this->rows('inquiries') as $r) {
            if ($r['listing_id']===null || empty($r['contact_email'])) {
                // Company-only inquiries need a different destination schema,
                // never attach them to an unrelated listing or invent an email.
                throw new RuntimeException('Unmapped company-only/customer inquiry requires manual adapter.');
            }
            $id=$this->insert('inquiries',array_merge([
                'listing_id'=>$this->mapped('listings',$r['listing_id']),
                'requester_id'=>$this->mapped('users',$r['user_id'],true),
                'full_name'=>$this->limited($r['contact_name'],120),
                'email'=>$this->limited($r['contact_email'],190),
                'phone'=>$this->limited($r['contact_phone'],50,true),
                'message'=>$r['message'],'status'=>'new',
                'responded_at'=>$r['responded_at'],
            ],$this->timestamps($r)));
            $this->map('inquiries',$r,$id,(string)$r['status']);
        }
    }

    private function verifyMappedCounts(): void
    {
        foreach (['users','locations','amenities','companies','projects',
            'properties','listings','media_assets','media_attachments','inquiries'] as $table) {
            if (count($this->maps[$table]??[])!==($this->counts[$table]??0)) {
                throw new RuntimeException('Conversion lost one or more source identities.');
            }
        }
        if ((int)$this->target->query('SELECT COUNT(*) FROM legacy_company_membership_reviews')
            ->fetchColumn()!==($this->counts['company_members']??0)) {
            throw new RuntimeException('Membership review mapping lost records.');
        }
    }
}
