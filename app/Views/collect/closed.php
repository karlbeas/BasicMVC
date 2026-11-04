<?php
/** @var string $title */
/** @var string $reason */
?>
<div class="mx-auto f2s-form-col text-center py-5">
    <h1 class="h3"><?= e($title) ?></h1>
    <p class="lead text-muted"><?= e(t('collect.closed.' . $reason)) ?></p>
</div>
