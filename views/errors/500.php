<?php
$message = (string) ($message ?? 'Please try again.');
$home = url('/products');
?>
<section class="error-page" aria-labelledby="error-page-title">
    <div class="error-page__card">
        <p class="error-page__code">500</p>
        <h1 id="error-page-title">Something went wrong</h1>
        <p class="error-page__copy"><?= e($message) ?></p>
        <div class="error-page__actions">
            <a class="button" href="<?= e($home) ?>"><?= icon('home', 15) ?> Back to products</a>
            <button type="button" class="button button-ghost" data-history-back><?= icon('arrow-left', 15) ?> Go back</button>
        </div>
    </div>
</section>
