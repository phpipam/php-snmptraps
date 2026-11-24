<?php
/**
 * Card footer with result range and pagination.
 *
 * @var int $page
 * @var int $pages
 * @var int $total
 * @var int $limit
 * @var string $q
 * @var string $base relative URL of the list (e.g. "severity/major/")
 */
$link = static function (int $p) use ($base, $q, $limit): string {
    $params = ['page' => $p];
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($limit !== 50) {
        $params['limit'] = $limit;
    }
    return e($base . '?' . http_build_query($params));
};

// window of up to 5 pages around the current one
$from = max(1, min($page - 2, $pages - 4));
$to   = min($pages, $from + 4);
$first = $total === 0 ? 0 : ($page - 1) * $limit + 1;
$last  = min($total, $page * $limit);
?>
<div class="card-footer d-flex align-items-center">
    <p class="m-0 text-secondary">Showing <span><?= $first ?></span> - <span><?= $last ?></span> of <span><?= number_format($total, 0, ',', '.') ?></span> traps</p>
    <?php if ($pages > 1): ?>
    <ul class="pagination m-0 ms-auto">
        <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= $link($page - 1) ?>"><i class="ti ti-chevron-left"></i></a></li>
        <?php if ($from > 1): ?>
        <li class="page-item"><a class="page-link" href="<?= $link(1) ?>">1</a></li>
        <?php if ($from > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $from; $i <= $to; $i++): ?>
        <li class="page-item<?= $i === $page ? ' active' : '' ?>"><a class="page-link" href="<?= $link($i) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
        <?php if ($to < $pages): ?>
        <?php if ($to < $pages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
        <li class="page-item"><a class="page-link" href="<?= $link($pages) ?>"><?= $pages ?></a></li>
        <?php endif; ?>
        <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= $link($page + 1) ?>"><i class="ti ti-chevron-right"></i></a></li>
    </ul>
    <?php endif; ?>
</div>
