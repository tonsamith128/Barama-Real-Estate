<?php

declare(strict_types=1);

$categories = is_array($categories ?? null) ? $categories : [];
$categoryUsage = is_array($categoryUsage ?? null) ? $categoryUsage : [];
$editingCategory = is_array($editingCategory ?? null) ? $editingCategory : null;

$totalAssigned = array_sum(array_map('intval', $categoryUsage));
$editingId = (string) ($editingCategory['id'] ?? '');
$editingUsage = $editingId !== ''
    ? (int) ($categoryUsage[$editingId] ?? 0)
    : 0;
?>
<?= barama_css_once('policy-center') ?>

<main class="pc-admin-main pc-category-manager">
    <header class="pc-admin-head">
        <div>
            <span class="pc-kicker">Policy Center</span>
            <h1>Policy Categories</h1>
            <p>
                Create reusable categories for legal policies and control how
                they appear inside the Policy Builder.
            </p>

            <div class="pc-admin-summary" aria-label="Category summary">
                <span>
                    <strong><?= number_format(count($categories)) ?></strong>
                    Categories
                </span>
                <span>
                    <strong><?= number_format($totalAssigned) ?></strong>
                    Policy assignments
                </span>
            </div>
        </div>

        <div class="pc-admin-head__actions">
            <a class="pc-btn" href="<?= e(url('/dashboard/policies')) ?>">
                ← Policies
            </a>

            <a class="pc-btn pc-primary" href="<?= e(url('/dashboard/policies/categories')) ?>">
                + Create Category
            </a>
        </div>
    </header>

    <?php if (isset($_GET['created'])): ?>
        <div class="pc-success">Category created successfully.</div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="pc-success">Category updated successfully.</div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="pc-success">Unused category deleted successfully.</div>
    <?php endif; ?>

    <?php if (isset($_GET['merged'])): ?>
        <div class="pc-success">
            Category merged successfully and assigned policies were moved.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['delete_blocked'])): ?>
        <div class="pc-warning">
            This category is still assigned to one or more policies.
            Merge it into another category before deleting it.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['merge_failed'])): ?>
        <div class="pc-error">
            The category could not be merged. Choose a different valid target.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="pc-error"><?= e((string) $_GET['error']) ?></div>
    <?php endif; ?>

    <div class="pc-category-layout">
        <section class="pc-category-list">
            <div class="pc-category-list__head">
                <div>
                    <h2>Categories</h2>
                    <p>Reusable taxonomy for Policy Center content.</p>
                </div>

                <label class="pc-search pc-category-search">
                    <span>Search</span>
                    <input
                        type="search"
                        placeholder="Search categories..."
                        data-pc-category-search
                    >
                </label>
            </div>

            <div class="pc-table-wrap">
                <table class="pc-table pc-category-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Category</th>
                            <th>Slug</th>
                            <th>Policies</th>
                            <th>Order</th>
                            <th>Updated</th>
                            <th class="pc-actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($categories === []): ?>
                            <tr>
                                <td colspan="7" class="pc-empty">
                                    <strong>No policy categories yet.</strong>
                                    <span>Create the first reusable category.</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $index => $category): ?>
                                <?php
                                $id = (string) ($category['id'] ?? '');
                                $name = (string) ($category['name'] ?? 'Untitled');
                                $slug = (string) ($category['slug'] ?? '');
                                $usage = (int) ($categoryUsage[$id] ?? 0);
                                $updated = strtotime((string) ($category['updated_at'] ?? ''));
                                $searchValue = mb_strtolower(
                                    $name . ' '
                                    . $slug . ' '
                                    . (string) ($category['description'] ?? ''),
                                    'UTF-8'
                                );
                                ?>
                                <tr data-pc-category-row data-search="<?= e($searchValue) ?>">
                                    <td><?= number_format($index + 1) ?></td>

                                    <td>
                                        <a
                                            class="pc-policy-title-link"
                                            href="<?= e(url('/dashboard/policies/categories?edit=' . rawurlencode($id))) ?>"
                                        ><?= e($name) ?></a>

                                        <?php if (trim((string) ($category['description'] ?? '')) !== ''): ?>
                                            <small>
                                                <?= e((string) ($category['description'] ?? '')) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>

                                    <td><code><?= e($slug) ?></code></td>

                                    <td>
                                        <span class="pc-category-count">
                                            <?= number_format($usage) ?>
                                        </span>
                                    </td>

                                    <td><?= number_format((int) ($category['sort_order'] ?? 0)) ?></td>
                                    <td><?= e($updated ? date('M j, Y', $updated) : '—') ?></td>

                                    <td class="pc-actions-cell">
                                        <a
                                            class="pc-category-edit"
                                            href="<?= e(url('/dashboard/policies/categories?edit=' . rawurlencode($id))) ?>"
                                        >Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <tr class="pc-no-results" data-pc-category-no-results hidden>
                            <td colspan="7">
                                <strong>No matching categories.</strong>
                                <span>Try a different category search.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="pc-category-form-panel">
            <div class="pc-category-form-panel__head">
                <span class="pc-kicker">
                    <?= $editingCategory !== null ? 'Edit Category' : 'New Category' ?>
                </span>
                <h2>
                    <?= $editingCategory !== null
                        ? e((string) ($editingCategory['name'] ?? 'Category'))
                        : 'Create Category' ?>
                </h2>
                <p>
                    Categories appear as a dropdown under Organization in the
                    Policy Builder.
                </p>
            </div>

            <form
                method="post"
                action="<?= e(url(
                    $editingCategory !== null
                        ? '/dashboard/policies/categories/update'
                        : '/dashboard/policies/categories/create'
                )) ?>"
                data-pc-category-form
            >
                <?= csrf_field() ?>

                <?php if ($editingCategory !== null): ?>
                    <input
                        type="hidden"
                        name="id"
                        value="<?= e($editingId) ?>"
                    >
                <?php endif; ?>

                <div class="pc-field">
                    <label for="pc-category-name">Category name</label>
                    <input
                        id="pc-category-name"
                        name="name"
                        required
                        maxlength="80"
                        value="<?= e((string) ($editingCategory['name'] ?? '')) ?>"
                        placeholder="e.g. Privacy & Data"
                        data-pc-category-name
                    >
                </div>

                <div class="pc-field">
                    <label for="pc-category-slug">Slug</label>
                    <div class="pc-slug pc-category-slug">
                        <span>category/</span>
                        <input
                            id="pc-category-slug"
                            name="slug"
                            maxlength="100"
                            value="<?= e((string) ($editingCategory['slug'] ?? '')) ?>"
                            placeholder="privacy-data"
                            data-pc-category-slug
                        >
                    </div>
                </div>

                <div class="pc-field">
                    <label for="pc-category-description">Description</label>
                    <textarea
                        id="pc-category-description"
                        name="description"
                        rows="4"
                        maxlength="360"
                        placeholder="Short internal description for administrators."
                    ><?= e((string) ($editingCategory['description'] ?? '')) ?></textarea>
                </div>

                <div class="pc-field">
                    <label for="pc-category-order">Display order</label>
                    <input
                        id="pc-category-order"
                        type="number"
                        name="sort_order"
                        min="0"
                        max="9999"
                        value="<?= e((string) ($editingCategory['sort_order'] ?? count($categories) * 10 + 10)) ?>"
                    >
                </div>

                <div class="pc-category-form-actions">
                    <?php if ($editingCategory !== null): ?>
                        <a class="pc-btn" href="<?= e(url('/dashboard/policies/categories')) ?>">
                            Cancel
                        </a>
                    <?php endif; ?>

                    <button class="pc-btn pc-primary" type="submit">
                        <?= $editingCategory !== null ? 'Save Category' : 'Create Category' ?>
                    </button>
                </div>
            </form>

            <?php if ($editingCategory !== null): ?>
                <div class="pc-category-usage-card">
                    <div>
                        <strong>Policy usage</strong>
                        <span>
                            <?= number_format($editingUsage) ?>
                            <?= $editingUsage === 1 ? 'policy' : 'policies' ?>
                        </span>
                    </div>

                    <?php if ($editingUsage > 0): ?>
                        <p>
                            This category cannot be deleted while in use.
                            Merge it into another category to safely reassign
                            all affected policies.
                        </p>

                        <form
                            method="post"
                            action="<?= e(url('/dashboard/policies/categories/merge')) ?>"
                            data-pc-category-merge
                        >
                            <?= csrf_field() ?>
                            <input type="hidden" name="source_id" value="<?= e($editingId) ?>">

                            <div class="pc-field">
                                <label>Merge into</label>
                                <select name="target_id" required>
                                    <option value="">Choose destination…</option>
                                    <?php foreach ($categories as $category): ?>
                                        <?php if ((string) ($category['id'] ?? '') === $editingId) continue; ?>
                                        <option value="<?= e((string) ($category['id'] ?? '')) ?>">
                                            <?= e((string) ($category['name'] ?? 'Category')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button class="pc-btn" type="submit">
                                Merge &amp; Reassign
                            </button>
                        </form>
                    <?php else: ?>
                        <p>
                            This category is unused and can be deleted safely.
                        </p>

                        <form
                            method="post"
                            action="<?= e(url('/dashboard/policies/categories/delete')) ?>"
                            data-pc-category-delete
                        >
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e($editingId) ?>">

                            <button class="pc-btn pc-danger-btn" type="submit">
                                Delete Category
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</main>

<?= barama_js_once('policy-center') ?><script type="application/x-barama-module" data-barama-module="policy-center/list-and-categories"></script>
