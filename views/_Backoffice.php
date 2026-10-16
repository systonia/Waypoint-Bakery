<?php
/**
 * The back office's frame: its navigation, the signed-in person, the flash messages.
 *
 * @var Bakery\Backoffice\User|Bakery\Backoffice\SalesPage|array{form: Bakery\Backoffice\LoginForm} $model the signed-in user, or a page that has one, or the sign-in form
 */
$user = $model instanceof Bakery\Backoffice\User ? $model : ($model->user ?? null);
?>
<!doctype html>
<html lang="<?= $this->locale() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->section('title') ?> · Sonnenkorn <?= $this->t('Back office') ?></title>
    <link rel="stylesheet" href="/app.css">
    <link rel="icon" href="/favicon.svg">
</head>
<body class="min-h-screen bg-white text-stone-900">
<?= $this->flashes() ?>
<header class="bg-stone-900 text-stone-100">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-6 px-4 py-3">
        <a href="/backoffice" class="font-semibold">Sonnenkorn · <?= $this->t('Back office') ?></a>
        <?php if ($user !== null): ?>
            <nav class="flex gap-4 text-sm">
                <a href="/backoffice"><?= $this->t('Orders') ?></a>
                <a href="/backoffice/stock"><?= $this->t('Stock') ?></a>
                <?php if ($user->isOwner()): ?>
                    <a href="/backoffice/sales"><?= $this->t('Sales') ?></a>
                <?php endif ?>
                <a href="/openapi/swagger.html"><?= $this->t('API') ?></a>
            </nav>
        <?php endif ?>
        <span class="ml-auto flex items-center gap-3 text-sm">
            <a href="?lang=en">EN</a> <a href="?lang=de">DE</a>
            <a href="/" class="text-stone-400"><?= $this->t('To the shop') ?></a>
            <?php if ($user !== null): ?>
                <form method="post" action="/backoffice/logout">
                    <?= $this->csrf->field() ?>
                    <button class="rounded bg-stone-700 px-2 py-1"><?= $this->t('Sign out {name}', ['name' => $user->name]) |> htmlspecialchars(...) ?></button>
                </form>
            <?php endif ?>
        </span>
    </div>
</header>
<main class="mx-auto max-w-7xl px-4 py-8"><?= $content ?></main>
<?= $this->waypointJsTag() ?>
<?= $this->pluginAssetTags() ?>
</body>
</html>
