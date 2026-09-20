<?php

declare(strict_types=1);

use Barama\Core\Database;
use Barama\Core\Session;
use Barama\Repositories\RoleRepository;
use Barama\Repositories\UserRepository;
use Barama\Repositories\WorkspaceReadRepository;
use Barama\Repositories\WorkspaceRecordRepository;
use Barama\Repositories\MarketplaceRepository;
use Barama\Services\BulkModerationService;
use Barama\Services\CatalogManagementService;
use Barama\Services\CompanyManagementService;
use Barama\Services\DomainWorkflowService;
use Barama\Services\InquiryService;
use Barama\Services\LeadManagementService;
use Barama\Services\ListingManagementService;
use Barama\Services\MediaStorageService;
use Barama\Services\MediaManagementService;
use Barama\Services\ProjectManagementService;
use Barama\Services\PropertyManagementService;
use DomainException;

define('BARAMA_CLI', true);

require dirname(__DIR__) . '/application/bootstrap/app.php';

$pdo = Database::connection();
$users = new UserRepository();
$roles = new RoleRepository();

$makeUser = static function (
    string $name,
    string $email,
    string $role
) use ($users, $roles): int {
    $hash = password_hash('Barama-QA-Password-123!', PASSWORD_DEFAULT);

    if (!is_string($hash)) {
        throw new RuntimeException('Unable to create QA password hash.');
    }

    $userId = $users->create($name, $email, $hash);
    $roles->assignRole($userId, 'user');

    if ($role !== 'user') {
        $roles->assignRole($userId, $role);
    }

    return $userId;
};

$adminId = $makeUser('QA Admin', 'qa-admin@example.test', 'administrator');
$developerId = $makeUser('QA Developer', 'qa-developer@example.test', 'developer');
$otherDeveloperId = $makeUser('QA Other Developer', 'qa-other@example.test', 'developer');

Session::put('auth.user_id', $adminId);

$catalog = new CatalogManagementService();
$countryId = $catalog->createLocation([
    'name' => 'Cambodia QA',
    'type' => 'country',
    'country_code' => 'KH',
    'latitude' => '12.5657',
    'longitude' => '104.9910',
]);
$cityId = $catalog->createLocation([
    'name' => 'Phnom Penh QA',
    'type' => 'city',
    'parent_id' => $countryId,
    'country_code' => 'KH',
    'latitude' => '11.5564',
    'longitude' => '104.9282',
]);
$catalog->createAmenity([
    'name' => 'Swimming Pool QA',
    'category' => 'leisure',
    'icon_key' => 'pool',
]);

Session::put('auth.user_id', $developerId);

$companyId = (new CompanyManagementService())->create([
    'name' => 'Barama QA Development',
    'company_type' => 'developer',
    'location_id' => $cityId,
    'email' => 'company@example.test',
]);

$projectId = (new ProjectManagementService())->create([
    'company_id' => $companyId,
    'location_id' => $cityId,
    'name' => 'Barama QA Residence',
    'project_type' => 'residential',
    'completion_status' => 'under_construction',
]);

$propertyId = (new PropertyManagementService())->create([
    'company_id' => $companyId,
    'project_id' => $projectId,
    'location_id' => $cityId,
    'title' => 'QA Riverside Condominium',
    'property_type' => 'condominium',
    'bedrooms' => '2',
    'bathrooms' => '2',
    'parking_spaces' => '0',
    'area_value' => '88.50',
    'area_unit' => 'sqm',
]);

$listingId = (new ListingManagementService())->create([
    'property_id' => $propertyId,
    'transaction_type' => 'sale',
    'price' => '150000',
    'currency' => 'USD',
    'negotiable' => true,
]);

$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl8sAAAAASUVORK5CYII=',
    true
);

if (!is_string($png)) {
    throw new RuntimeException('Unable to prepare QA image.');
}

$tmp = tempnam(sys_get_temp_dir(), 'barama-media-');

if ($tmp === false) {
    throw new RuntimeException('Unable to create QA media temp file.');
}

file_put_contents($tmp, $png);

$mediaId = (new MediaStorageService())->storeAndAttach([
    'name' => 'qa-image.png',
    'tmp_name' => $tmp,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmp),
], [
    'entity_type' => 'listing',
    'entity_id' => $listingId,
    'collection' => 'gallery',
    'is_primary' => false,
    'alt_text' => 'QA listing image',
]);


$attachmentStatement = $pdo->prepare(
    'SELECT id FROM media_attachments WHERE media_asset_id = :media_id LIMIT 1'
);
$attachmentStatement->execute(['media_id' => $mediaId]);
$attachmentId = (int) $attachmentStatement->fetchColumn();

if ($attachmentId < 1) {
    throw new RuntimeException('Media attachment was not created.');
}

(new MediaManagementService())->setPrimary($attachmentId);

$mediaUuidStatement = $pdo->prepare('SELECT uuid FROM media_assets WHERE id = :id LIMIT 1');
$mediaUuidStatement->execute(['id' => $mediaId]);
$mediaUuid = (string) $mediaUuidStatement->fetchColumn();
$marketplace = new MarketplaceRepository();

if ($mediaUuid === '' || $marketplace->publicMedia($mediaUuid) !== null) {
    throw new RuntimeException('Draft media became public before marketplace publication.');
}

$recordReader = new WorkspaceRecordRepository();
$draftRecord = $recordReader->developerRecord($developerId, 'listing', $listingId);

if ($draftRecord === null || count($draftRecord['media']) !== 1) {
    throw new RuntimeException('Developer record detail did not return scoped draft media.');
}

$workflow = new DomainWorkflowService();
$workflow->transition('company', $companyId, 'pending', 'QA submit');
$workflow->transition('project', $projectId, 'pending', 'QA submit');
$workflow->transition('property', $propertyId, 'pending', 'QA submit');
$workflow->transition('listing', $listingId, 'pending', 'QA submit');

Session::put('auth.user_id', $adminId);
$workflow->transition('company', $companyId, 'approved', 'QA approve');
$workflow->transition('project', $projectId, 'approved', 'QA approve');
$workflow->transition('project', $projectId, 'published', 'QA publish');
$workflow->transition('property', $propertyId, 'approved', 'QA approve');
$workflow->transition('listing', $listingId, 'published', 'QA publish');

$inquiryId = (new InquiryService())->capture([
    'website' => '',
    'target_type' => 'listing',
    'target_id' => $listingId,
    'contact_name' => 'QA Buyer',
    'contact_email' => 'buyer@example.test',
    'preferred_contact' => 'email',
    'message' => 'I would like more information about this property listing.',
], '127.0.0.77');

Session::put('auth.user_id', $developerId);
$leadId = (new LeadManagementService())->convertInquiry($inquiryId, [
    'budget_min' => '140000',
    'budget_max' => '160000',
    'currency' => 'USD',
    'transaction_type' => 'sale',
    'property_type' => 'condominium',
]);

$workflow->transition('lead', $leadId, 'contacted', 'QA follow-up');

$listingEditLocked = false;
try {
    (new ListingManagementService())->update($listingId, [
        'transaction_type' => 'sale',
        'price' => '149000',
        'currency' => 'USD',
    ]);
} catch (DomainException) {
    $listingEditLocked = true;
}

$companyEditLocked = false;
try {
    (new CompanyManagementService())->update($companyId, [
        'name' => 'Barama QA Development Changed',
        'company_type' => 'developer',
    ]);
} catch (DomainException) {
    $companyEditLocked = true;
}

$mediaEditLocked = false;
try {
    (new MediaStorageService())->storeAndAttach([], [
        'entity_type' => 'listing',
        'entity_id' => $listingId,
    ]);
} catch (DomainException) {
    $mediaEditLocked = true;
}

if (!$listingEditLocked || !$companyEditLocked || !$mediaEditLocked) {
    throw new RuntimeException('Reviewed-content edit locks were not enforced.');
}

Session::put('auth.user_id', $otherDeveloperId);

$scopeBlocked = false;

try {
    (new CompanyManagementService())->update($companyId, [
        'name' => 'Unauthorized Change',
        'company_type' => 'developer',
    ]);
} catch (DomainException) {
    $scopeBlocked = true;
}

if (!$scopeBlocked) {
    throw new RuntimeException('Company scope isolation failed.');
}

if ($recordReader->developerRecord($otherDeveloperId, 'listing', $listingId) !== null) {
    throw new RuntimeException('Developer record detail leaked another company listing.');
}

if ($recordReader->adminRecord('listing', $listingId) === null) {
    throw new RuntimeException('Admin record detail could not load a platform listing.');
}

$historyCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM entity_status_history'
)->fetchColumn();

if ($historyCount < 9) {
    throw new RuntimeException('Expected workflow status history was not recorded.');
}

if ($mediaId < 1 || $leadId < 1) {
    throw new RuntimeException('Domain write chain did not create expected records.');
}

$workspace = new WorkspaceReadRepository();
$developerSummary = $workspace->developerSummary($developerId);
$developerListings = $workspace->developerSection($developerId, 'listings', '', 1, 20);
$adminSummary = $workspace->adminSummary();
$adminCompanies = $workspace->adminSection('companies', '', 1, 20);

if (
    $developerSummary['companies'] < 1
    || $developerSummary['properties'] < 1
    || $developerSummary['published_listings'] < 1
    || (int) $developerListings['total'] < 1
) {
    throw new RuntimeException('Developer workspace read model did not return scoped domain records.');
}

if (
    $adminSummary['companies'] < 1
    || $adminSummary['properties'] < 1
    || $adminSummary['published_listings'] < 1
    || (int) $adminCompanies['total'] < 1
) {
    throw new RuntimeException('Admin workspace read model did not return platform records.');
}

$publicListings = $marketplace->listings(['offer' => 'sale'], 1, 10);
$publicProjects = $marketplace->projects([], 1, 10);
$publicCompanies = $marketplace->companies([], 1, 10);
if (
    (int) $publicListings['total'] < 1
    || (int) $publicProjects['total'] < 1
    || (int) $publicCompanies['total'] < 1
    || $marketplace->listingBySlug((string) $publicListings['rows'][0]['slug']) === null
    || $marketplace->projectBySlug((string) $publicProjects['rows'][0]['slug']) === null
    || $marketplace->companyBySlug((string) $publicCompanies['rows'][0]['slug']) === null
    || $mediaUuid === ''
    || $marketplace->publicMedia($mediaUuid) === null
) {
    throw new RuntimeException('Public marketplace read model or guarded public media lookup failed.');
}

$filteredDeveloperListings = $workspace->developerSection(
    $developerId,
    'listings',
    '',
    1,
    20,
    [
        'status' => 'published',
        'type' => 'sale',
        'location' => (string) $cityId,
    ],
    'price_desc'
);

$filteredAdminCompanies = $workspace->adminSection(
    'companies',
    '',
    1,
    20,
    [
        'status' => 'approved',
        'type' => 'developer',
        'location' => (string) $cityId,
    ],
    'name_asc'
);

if (
    (int) $filteredDeveloperListings['total'] < 1
    || (int) $filteredAdminCompanies['total'] < 1
) {
    throw new RuntimeException('Workspace table filtering or sorting removed valid scoped records.');
}

Session::put('auth.user_id', $developerId);

$bulkCompanyId = (new CompanyManagementService())->create([
    'name' => 'Barama QA Bulk Development',
    'company_type' => 'developer',
    'location_id' => $cityId,
    'email' => 'bulk-company@example.test',
]);

$workflow->transition('company', $bulkCompanyId, 'pending', 'QA bulk submit');

Session::put('auth.user_id', $adminId);

$bulkResult = (new BulkModerationService())->moderate(
    ['company:' . $bulkCompanyId],
    'approve',
    'QA bulk approval'
);

$bulkRecord = $recordReader->adminRecord('company', $bulkCompanyId);

if (
    $bulkResult['processed'] !== 1
    || $bulkResult['succeeded'] !== 1
    || $bulkResult['failed'] !== 0
    || $bulkRecord === null
    || (string) $bulkRecord['record']['status'] !== 'approved'
) {
    throw new RuntimeException('Bulk moderation did not approve the pending company.');
}

fwrite(
    STDOUT,
    "[PASS] Domain smoke test: writes, workflows, scoped record details, review locks, filtered workspace reads, bulk moderation, public marketplace reads, guarded media and company-scope isolation verified.\n"
);
