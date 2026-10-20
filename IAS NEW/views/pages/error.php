<section class="empty-state">
    <p class="eyebrow">Error <?= (int) $code ?></p>
    <h1><?= e($heading) ?></h1>
    <p class="muted"><?= e($message) ?></p>
    <a class="btn btn-primary" href="<?= e($homeHref) ?>">Go to my dashboard</a>
</section>
