<?php
/** @var App\Model\PageResult $pageResult */
/** @var App\Model\ProductQuery $query */
/** @var string $basePath */
if ($pageResult->lastPage() <= 1) {
    return;
}
?>
<nav class="pager" aria-label="Pagination">
    <?php for ($i = 1; $i <= $pageResult->lastPage(); $i++): ?>
        <?php
        $href = url($basePath) . '?' . http_build_query(array_filter([
            'page' => $i,
            'sort' => $query->sort,
            'dir' => $query->direction,
            'per_page' => $query->perPage,
            'q' => $query->search !== '' ? $query->search : null,
            'category' => $query->category !== '' ? $query->category : null,
        ]));
        ?>
        <a class="<?= $i === $pageResult->page ? 'is-current' : '' ?>" href="<?= e($href) ?>"><?= e((string) $i) ?></a>
    <?php endfor; ?>
</nav>
