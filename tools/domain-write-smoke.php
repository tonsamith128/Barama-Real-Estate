<?php

declare(strict_types=1);

use Barama\Core\Database;
use Barama\Core\Session;
use Barama\Repositories\RoleRepository;
use Barama\Repositories\UserRepository;
use Barama\Services\CatalogManagementService;
use Barama\Services\CompanyManagementService;
use Barama\Services\DomainWorkflowService;
use Barama\Services\InquiryService;
use Barama\Services\LeadManagementService;
use Barama\Services\ListingManagementService;
use Barama\Services\MediaStorageService;
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
    'is_primary' => true,
    'alt_text' => 'QA listing image',
]);

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

$historyCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM entity_status_history'
)->fetchColumn();

if ($historyCount < 9) {
    throw new RuntimeException('Expected workflow status history was not recorded.');
}

if ($mediaId < 1 || $leadId < 1) {
    throw new RuntimeException('Domain write chain did not create expected records.');
}

fwrite(
    STDOUT,
    "[PASS] Domain write smoke test: company, project, property, listing, inquiry, lead, media, workflow and company-scope isolation verified.\n"
);
