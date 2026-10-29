<?php

declare(strict_types=1);

$companies = is_array($formOptions['companies'] ?? null) ? $formOptions['companies'] : [];
$projects = is_array($formOptions['projects'] ?? null) ? $formOptions['projects'] : [];
$properties = is_array($formOptions['properties'] ?? null) ? $formOptions['properties'] : [];
$humanize = static fn (mixed $value): string => ucwords(str_replace('_', ' ', trim((string) $value)));
$createBack = $createSection === 'company' ? '/developer/company' : '/developer/' . $createSection;
$createRequirements = [
    'company' => 'Company profiles start as drafts and follow the existing moderation workflow.',
    'projects' => 'Choose a company you manage. Projects follow the existing approval and publishing workflow.',
    'properties' => 'Property records are canonical; create sale or rental listings separately after approval.',
    'listings' => 'Sale or rental offers belong to an existing property. Rental offers require a pricing period.',
];
$needsCompany = in_array($createSection, ['projects','properties'], true) && $companies === [];
$needsProperty = $createSection === 'listings' && $properties === [];
?>
<?= barama_css_once('developer') ?>
<?= barama_js_once('admin') ?><script type="application/x-barama-module" data-barama-module="global-location-cascade"></script>
<?= barama_css_once('admin') ?>
<?= barama_js_once('developer') ?><script type="application/x-barama-module" data-barama-module="developer-create-pages"></script>
<section class="workspace-page developer-create-page" data-developer-create-page>
    <header class="workspace-page__header developer-create-page__header">
        <div>
            <span class="eyebrow">Company / Vendor Workspace</span>
            <h1><?= e($createTitle) ?></h1>
            <p><?= e($createDescription) ?></p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(url($createBack)) ?>">
            <span aria-hidden="true">←</span><span>Back to <?= e($createSection === 'company' ? 'companies' : $createSection) ?></span>
        </a>
    </header>
    <div class="developer-create-page__shell">
        <section class="developer-create-page__card" aria-label="<?= e($createTitle) ?> form">
            <div class="developer-create-page__intro">
                <span class="developer-create-page__step">01</span>
                <div><h2><?= e($createTitle) ?> details</h2><p>Complete the required fields, then save the new record.</p></div>
            </div>
            <?php if ($needsCompany || $needsProperty): ?>
                <div class="developer-create-page__notice" role="status">
                    <?= app_icon('alert') ?>
                    <div><strong><?= $needsCompany ? 'A company is required first' : 'A property is required first' ?></strong>
                    <p><?= $needsCompany ? 'Create a company before adding projects or properties.' : 'Create a property before adding a sale or rental listing.' ?></p>
                    <a href="<?= e(url($needsCompany ? '/developer/company/new' : '/developer/properties/new')) ?>">
                        <?= e($needsCompany ? 'Add company' : 'Add property') ?> →
                    </a></div>
                </div>
            <?php else: ?>
                <div class="developer-create-page__feedback" role="status" aria-live="polite" tabindex="-1" data-create-feedback hidden></div>
                <?php if ($createSection === 'company'): ?>
<form action="<?= e(url('/developer/companies')) ?>" method="post" data-developer-create-form class="workspace-form developer-create-form" data-create-resource="<?= e($createResource) ?>" data-success-path="<?= e(url('/developer/manage/' . $createResource . '/__RECORD_ID__')) ?>">
                <?= csrf_field() ?>
                <div class="workspace-form__grid">
                    <label><span>Company name</span><input class="form-control" name="name" required maxlength="180"></label>
                    <label><span>Company type</span>
                        <select class="form-select" name="company_type" required>
                            <option value="developer">Developer</option>
                            <option value="agency">Agency</option>
                            <option value="property_company">Property company</option>
                            <option value="owner">Owner</option>
                        </select>
                    </label>
                    <label><span>Email</span><input class="form-control" name="email" type="email" maxlength="191"></label>
                    <label><span>Phone</span><input class="form-control" name="phone" maxlength="60"></label>
                    <?= \Barama\Core\View::partial('workspace/location-cascade', [
    'fieldName' => 'location_id',
    'currentLocationId' => 0,
    'label' => 'Location',
    'spanTwo' => true,
]) ?>
                </div>
                <div class="developer-create-form__actions"><a class="btn btn-outline-secondary" href="<?= e(url($createBack)) ?>">Cancel</a><button class="btn btn-primary" type="submit" data-create-submit>Create company</button></div>
            </form>
                <?php elseif ($createSection === 'projects'): ?>
<form action="<?= e(url('/developer/projects')) ?>" method="post" data-developer-create-form class="workspace-form developer-create-form" data-create-resource="<?= e($createResource) ?>" data-success-path="<?= e(url('/developer/manage/' . $createResource . '/__RECORD_ID__')) ?>">
                <?= csrf_field() ?>
                <div class="workspace-form__grid">
                    <label><span>Company</span>
                        <select class="form-select" name="company_id" required>
                            <option value="">Select company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= e((string) $company['id']) ?>"><?= e((string) $company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Project name</span><input class="form-control" name="name" required maxlength="191"></label>
                    <label><span>Project type</span>
                        <select class="form-select" name="project_type" required>
                            <option value="residential">Residential</option>
                            <option value="commercial">Commercial</option>
                            <option value="mixed_use">Mixed use</option>
                            <option value="land_development">Land development</option>
                            <option value="industrial">Industrial</option>
                            <option value="hospitality">Hospitality</option>
                            <option value="other">Other</option>
                        </select>
                    </label>
                    <label><span>Completion</span>
                        <select class="form-select" name="completion_status" required>
                            <option value="planned">Planned</option>
                            <option value="pre_launch">Pre-launch</option>
                            <option value="under_construction">Under construction</option>
                            <option value="completed">Completed</option>
                            <option value="on_hold">On hold</option>
                        </select>
                    </label>
                    <?= \Barama\Core\View::partial('workspace/location-cascade', [
    'fieldName' => 'location_id',
    'currentLocationId' => 0,
    'label' => 'Location',
    'spanTwo' => true,
]) ?>
                </div>
                <div class="developer-create-form__actions"><a class="btn btn-outline-secondary" href="<?= e(url($createBack)) ?>">Cancel</a><button class="btn btn-primary" type="submit" data-create-submit>Create project</button></div>
            </form>
                <?php elseif ($createSection === 'properties'): ?>
<form action="<?= e(url('/developer/properties')) ?>" method="post" data-developer-create-form class="workspace-form developer-create-form" data-create-resource="<?= e($createResource) ?>" data-success-path="<?= e(url('/developer/manage/' . $createResource . '/__RECORD_ID__')) ?>">
                <?= csrf_field() ?>
                <div class="workspace-form__grid">
                    <label><span>Company</span>
                        <select class="form-select" name="company_id" required>
                            <option value="">Select company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= e((string) $company['id']) ?>"><?= e((string) $company['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Title</span><input class="form-control" name="title" required maxlength="191"></label>
                    <label><span>Property type</span>
                        <select class="form-select" name="property_type" required>
                            <?php foreach (['apartment','condominium','house','villa','townhouse','land','office','retail','warehouse','industrial','hotel','commercial','other'] as $type): ?>
                                <option value="<?= e($type) ?>"><?= e($humanize($type)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Project</span>
                        <select class="form-select" name="project_id">
                            <option value="">Standalone property</option>
                            <?php foreach ($projects as $project): ?>
                                <option data-company-id="<?= e((string) $project['company_id']) ?>" value="<?= e((string) $project['id']) ?>"><?= e((string) $project['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?= \Barama\Core\View::partial('workspace/location-cascade', [
    'fieldName' => 'location_id',
    'currentLocationId' => 0,
    'label' => 'Location',
    'spanTwo' => true,
]) ?>
                    <label><span>Bedrooms</span><input class="form-control" name="bedrooms" type="number" min="0" step="0.5"></label>
                    <label><span>Bathrooms</span><input class="form-control" name="bathrooms" type="number" min="0" step="0.5"></label>
                    <label><span>Area</span><input class="form-control" name="area_value" type="number" min="0" step="0.01"></label>
                    <label><span>Area unit</span><input class="form-control" name="area_unit" value="sqm" maxlength="20"></label>
                </div>
                <div class="developer-create-form__actions"><a class="btn btn-outline-secondary" href="<?= e(url($createBack)) ?>">Cancel</a><button class="btn btn-primary" type="submit" data-create-submit>Create property</button></div>
            </form>
                <?php elseif ($createSection === 'listings'): ?>
<form action="<?= e(url('/developer/listings')) ?>" method="post" data-developer-create-form class="workspace-form developer-create-form" data-create-resource="<?= e($createResource) ?>" data-success-path="<?= e(url('/developer/manage/' . $createResource . '/__RECORD_ID__')) ?>">
                <?= csrf_field() ?>
                <div class="workspace-form__grid">
                    <label class="workspace-form__span-2"><span>Property</span>
                        <select class="form-select" name="property_id" required>
                            <option value="">Select property</option>
                            <?php foreach ($properties as $property): ?>
                                <option value="<?= e((string) $property['id']) ?>">
                                    <?= e((string) $property['reference_code']) ?> · <?= e((string) $property['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Offer</span>
                        <select class="form-select" name="transaction_type" required data-transaction-type>
                            <option value="sale">For sale</option>
                            <option value="rent">For rent</option>
                        </select>
                    </label>
                    <label><span>Price</span><input class="form-control" name="price" type="number" min="0" step="0.01" required></label>
                    <label><span>Currency</span><input class="form-control" name="currency" value="<?= e((string) (($workspacePreferences['default_currency'] ?? 'USD'))) ?>" maxlength="3" required></label>
                    <label><span>Pricing period</span>
                        <select class="form-select" name="pricing_period" data-pricing-period>
                            <option value="">Not applicable</option>
                            <option value="month">Monthly</option>
                            <option value="week">Weekly</option>
                            <option value="day">Daily</option>
                            <option value="year">Yearly</option>
                        </select>
                    </label>
                </div>
                <div class="developer-create-form__actions"><a class="btn btn-outline-secondary" href="<?= e(url($createBack)) ?>">Cancel</a><button class="btn btn-primary" type="submit" data-create-submit>Create listing</button></div>
            </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <aside class="developer-create-page__help">
            <div class="developer-create-page__help-icon"><?= app_icon('shield') ?></div>
            <strong>Company-scoped workflow</strong>
            <p><?= e($createRequirements[$createSection] ?? '') ?></p>
            <p>Existing validation, permissions, and subscription limits remain enforced when you save.</p>
        </aside>
    </div>
</section>
