<?php
/** @var int $status */
/** @var string $message */
/** @var string|null $detail */
$title = (string) $status;
?>
<div class="text-center py-5">
    <div class="display-1 fw-bold text-f2s"><?= e($status) ?></div>
    <p class="lead"><?= e($message) ?></p>
    <?php if ($detail !== null): ?><pre class="text-start small bg-light p-3 border"><?= e($detail) ?></pre><?php endif; ?>
    <a href="<?= e(url('')) ?>" class="btn btn-outline-secondary mt-3"><?= e(t('error.back_home')) ?></a>
</div>
