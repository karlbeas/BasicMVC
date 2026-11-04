<?php /** @var string $title */ ?>
<div class="mx-auto f2s-form-col text-center py-5">
    <div class="display-6 text-f2s mb-3" aria-hidden="true">✓</div>
    <h1 class="h3"><?= e(t('collect.thanks_title')) ?></h1>
    <p class="text-muted"><?= e(t('collect.thanks_text', ['title' => $title])) ?></p>
</div>
