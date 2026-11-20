<?php
/** @var list<App\Model\Category> $categories */
/** @var array<int, int> $productCounts */
/** @var array<int, list<App\Model\Product>> $productsByCategory */
/** @var array<string, string> $old */
/** @var array<string, string> $errors */

$productsByCategory = is_array($productsByCategory ?? null) ? $productsByCategory : [];

$total = count($categories);
$used = 0;
foreach ($productCounts as $count) {
    if ($count > 0) {
        $used++;
    }
}
$nameValue = (string) ($old['name'] ?? '');
?>
<section class="admin-categories">
    <div class="admin-users-stats">
        <article class="dash-stat dash-stat--mint">
            <div>
                <p>Total Categories</p>
                <strong><?= e((string) $total) ?></strong>
            </div>
            <span class="dash-stat-icon"><?= icon('tag', 18) ?></span>
        </article>
        <article class="dash-stat dash-stat--sky">
            <div>
                <p>In Use</p>
                <strong><?= e((string) $used) ?></strong>
            </div>
            <span class="dash-stat-icon"><?= icon('box', 18) ?></span>
        </article>
        <article class="dash-stat dash-stat--peach">
            <div>
                <p>Unused</p>
                <strong><?= e((string) max(0, $total - $used)) ?></strong>
            </div>
            <span class="dash-stat-icon"><?= icon('list', 18) ?></span>
        </article>
    </div>

    <div class="admin-categories-layout">
        <div class="admin-categories-card">
            <div class="admin-categories-card-head">
                <div>
                    <h2>All categories</h2>
                    <p>Add, rename, or remove categories used by products.</p>
                </div>
            </div>

            <?php if ($categories === []): ?>
                <div class="admin-users-empty">
                    <p>No categories yet. Add your first one.</p>
                </div>
            <?php else: ?>
                <div class="admin-users-table-wrap">
                    <table class="admin-users-table admin-categories-table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Products</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <?php $count = (int) ($productCounts[(int) $category->id] ?? 0); ?>
                                <tr>
                                    <td>
                                        <form class="admin-category-edit" method="post" action="<?= e(url('/admin/categories/' . $category->id . '/update')) ?>">
                                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                            <span class="admin-cat-pill"><?= e(mb_strtoupper(mb_substr($category->name, 0, 1))) ?></span>
                                            <input
                                                type="text"
                                                name="name"
                                                value="<?= e($category->name) ?>"
                                                maxlength="50"
                                                required
                                                aria-label="Name for <?= e($category->name) ?>"
                                            >
                                            <button type="submit" class="admin-icon-btn" title="Save" aria-label="Save <?= e($category->name) ?>">
                                                <?= icon('save', 14) ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <span class="admin-category-count"><?= e((string) $count) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $confirm = $count > 0
                                            ? 'These products in ' . $category->name . ' will be permanently deleted with the category.'
                                            : 'Delete category ' . $category->name . '?';
                                        $confirmItems = [];
                                        foreach (($productsByCategory[(int) $category->id] ?? []) as $product) {
                                            $confirmItems[] = [
                                                'name' => $product->name,
                                                'image' => product_image($product->slug),
                                                'initial' => mb_strtoupper(mb_substr($product->name !== '' ? $product->name : 'P', 0, 1)),
                                            ];
                                        }
                                        ?>
                                        <form
                                            method="post"
                                            action="<?= e(url('/admin/categories/' . $category->id . '/delete')) ?>"
                                            data-confirm="<?= e($confirm) ?>"
                                            data-confirm-items="<?= e(json_for_script($confirmItems)) ?>"
                                        >
                                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                            <button type="submit" class="admin-icon-btn is-danger" title="Delete" aria-label="Delete <?= e($category->name) ?>">
                                                <?= icon('trash', 14) ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <aside class="admin-categories-create">
            <h2>Add category</h2>
            <p>Create a new category for products to use.</p>
            <form method="post" action="<?= e(url('/admin/categories')) ?>" data-validate="category" novalidate>
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <div class="field" data-field="name">
                    <label for="category_name">Name</label>
                    <input id="category_name" name="name" value="<?= e($nameValue) ?>" required maxlength="50" placeholder="e.g. Candy" autocomplete="off">
                    <?php if (!empty($errors['name'])): ?>
                        <p class="field-error"><?= e($errors['name']) ?></p>
                    <?php endif; ?>
                </div>
                <button type="submit" class="button button-small">
                    <?= icon('plus', 15) ?> Add category
                </button>
            </form>
        </aside>
    </div>
</section>
