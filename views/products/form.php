<?php
/** @var App\Model\Product|null $product */
/** @var list<App\Model\Category> $categories */
/** @var string $heading */
/** @var string $action */
/** @var array<string, string> $old */
/** @var array<string, string> $errors */

$categories = is_array($categories ?? null) ? $categories : [];
$categoryNames = array_map(static fn (App\Model\Category $item): string => $item->name, $categories);
$name = (string) ($old['name'] ?? $product?->name ?? '');
$price = (string) ($old['price'] ?? $product?->price ?? '');
$quantity = (string) ($old['quantity_available'] ?? ($product !== null ? (string) $product->quantityAvailable : ''));
$reorderPoint = (string) ($old['reorder_point'] ?? ($product !== null ? (string) $product->reorderPoint : '5'));
$category = (string) ($old['category'] ?? $product?->category ?? ($categoryNames[0] ?? ''));
if ($category !== '' && !in_array($category, $categoryNames, true)) {
    $categoryNames[] = $category;
}
$currentImage = $product !== null ? product_image($product->slug) : null;
$isEdit = $product !== null;
?>
<section class="admin-product-form">
    <div class="admin-product-form-card">
        <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" data-validate="product" class="admin-product-form-body" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

            <label class="admin-product-dropzone <?= $currentImage ? 'has-image' : '' ?>" for="product_image" data-image-dropzone>
                <input type="file" id="product_image" name="image" accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp" hidden>
                <div class="admin-product-dropzone-preview" data-image-preview>
                    <?php if ($currentImage !== null): ?>
                        <img src="<?= e($currentImage) ?>" alt="">
                    <?php else: ?>
                        <span class="admin-product-dropzone-empty" aria-hidden="true"><?= icon('upload', 22) ?></span>
                    <?php endif; ?>
                </div>
                <span class="admin-product-dropzone-copy">
                    <strong data-image-label><?= $currentImage ? 'Change image' : 'Upload image' ?></strong>
                    <small>PNG, JPG, WebP · 2MB</small>
                </span>
                <?php if (!empty($errors['image'])): ?>
                    <p class="field-error"><?= e($errors['image']) ?></p>
                <?php endif; ?>
            </label>

            <div class="admin-product-fields">
                <div class="field" data-field="name">
                    <label for="name">Product name</label>
                    <input id="name" name="name" value="<?= e($name) ?>" required maxlength="150" placeholder="e.g. Coca-Cola" autocomplete="off">
                    <?php if (!empty($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
                </div>

                <div class="admin-product-field-row">
                    <div class="field" data-field="price">
                        <label for="price">Price (USD)</label>
                        <div class="admin-input-affix">
                            <span aria-hidden="true">$</span>
                            <input id="price" name="price" value="<?= e($price) ?>" required inputmode="decimal" placeholder="0.00">
                        </div>
                        <?php if (!empty($errors['price'])): ?><p class="field-error"><?= e($errors['price']) ?></p><?php endif; ?>
                    </div>
                    <div class="field" data-field="quantity_available">
                        <label for="quantity_available">Stock</label>
                        <input id="quantity_available" name="quantity_available" value="<?= e((string) $quantity) ?>" required inputmode="numeric" placeholder="0">
                        <?php if (!empty($errors['quantity_available'])): ?><p class="field-error"><?= e($errors['quantity_available']) ?></p><?php endif; ?>
                    </div>
                </div>

                <div class="admin-product-field-row">
                    <div class="field" data-field="reorder_point">
                        <label for="reorder_point">Reorder point</label>
                        <input id="reorder_point" name="reorder_point" value="<?= e($reorderPoint) ?>" required inputmode="numeric" placeholder="5">
                        <p class="admin-product-meta">Low-stock alert when stock reaches this level.</p>
                        <?php if (!empty($errors['reorder_point'])): ?><p class="field-error"><?= e($errors['reorder_point']) ?></p><?php endif; ?>
                    </div>
                    <div class="field" data-field="category">
                        <label for="category">Category</label>
                        <?php if ($categoryNames === []): ?>
                            <p class="admin-product-meta">No categories yet. <a href="<?= e(url('/admin/categories')) ?>">Create one</a> first.</p>
                            <input type="hidden" name="category" value="">
                        <?php else: ?>
                            <select id="category" name="category" required>
                                <?php foreach ($categoryNames as $option): ?>
                                    <option value="<?= e($option) ?>" <?= $category === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <?php if (!empty($errors['category'])): ?><p class="field-error"><?= e($errors['category']) ?></p><?php endif; ?>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                    <p class="admin-product-meta">Slug <code><?= e($product->slug) ?></code></p>
                <?php endif; ?>

                <div class="admin-product-form-actions">
                    <button type="submit" class="button">
                        <?= $isEdit ? icon('save', 15) . ' Save changes' : icon('plus', 15) . ' Add product' ?>
                    </button>
                    <a class="button button-ghost" href="<?= e(url('/admin/products')) ?>">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</section>

<script nonce="<?= e(csp_nonce()) ?>">
(() => {
    const input = document.getElementById('product_image');
    const preview = document.querySelector('[data-image-preview]');
    const label = document.querySelector('[data-image-label]');
    const dropzone = document.querySelector('[data-image-dropzone]');
    if (!input || !preview || !label || !dropzone) return;

    const showPreview = (file) => {
        if (!file || !file.type.startsWith('image/')) return;
        preview.innerHTML = '<img src="' + URL.createObjectURL(file) + '" alt="">';
        label.textContent = 'Change image';
        dropzone.classList.add('has-image');
    };

    input.addEventListener('change', () => {
        if (input.files && input.files[0]) showPreview(input.files[0]);
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-dragover');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        const files = event.dataTransfer && event.dataTransfer.files;
        if (!files || !files[0]) return;
        const transfer = new DataTransfer();
        transfer.items.add(files[0]);
        input.files = transfer.files;
        showPreview(files[0]);
    });
})();
</script>
