<?php $title = t('app.tagline'); ?>
<section class="py-5 text-center">
    <h1 class="display-5 fw-bold">F2S Pro</h1>
    <p class="lead text-muted mb-1">Field, Form &amp; Survey Pro</p>
    <p class="fs-4 fw-semibold text-f2s"><?= e(t('app.slogan')) ?></p>
    <p class="col-lg-8 mx-auto"><?= e(t('home.pitch')) ?></p>
    <div class="d-flex gap-2 justify-content-center mt-4">
        <a href="<?= e(url('register')) ?>" class="btn btn-f2s btn-lg"><?= e(t('home.cta_start')) ?></a>
        <a href="<?= e(url('login')) ?>" class="btn btn-outline-secondary btn-lg"><?= e(t('nav.login')) ?></a>
    </div>
</section>
<section class="row g-4 py-4">
    <?php foreach (['create', 'collect', 'analyze', 'report'] as $key): ?>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <h2 class="h5"><?= e(t('home.feature.' . $key . '.title')) ?></h2>
                    <p class="card-text text-muted"><?= e(t('home.feature.' . $key . '.text')) ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</section>
