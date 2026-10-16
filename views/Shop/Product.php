<?php
/** @var Bakery\Shop\ProductPage $model */
use Bakery\Format;

$locale = $this->locale();
$product = $model->product;
$sky = fn (int $code): string => match (true) {
    $code === 0 => '☀️ ' . $this->t('Sunny'),
    $code <= 3 => '⛅ ' . $this->t('Some clouds'),
    $code <= 48 => '🌫️ ' . $this->t('Fog'),
    $code <= 67, $code >= 80 && $code <= 82 => '🌧️ ' . $this->t('Rain'),
    $code <= 77, $code <= 86 => '❄️ ' . $this->t('Snow'),
    default => '⛈️ ' . $this->t('Thunderstorms'),
};
?>
<?php $this->startSection('title') ?><?= $product->name($locale) |> htmlspecialchars(...) ?><?php $this->endSection() ?>
<a href="/" class="text-sm text-stone-500 underline"><?= $this->t('All products') ?></a>
<div class="mt-4 grid gap-8 md:grid-cols-2">
    <img src="/products/<?= $product->picture ?>" alt="" class="w-full rounded-2xl bg-amber-50 p-8">
    <div>
        <h1 class="text-3xl font-semibold"><?= $product->name($locale) |> htmlspecialchars(...) ?></h1>
        <p class="mt-3 text-stone-700"><?= $product->description($locale) |> htmlspecialchars(...) ?></p>
        <p class="mt-4 text-2xl font-semibold tabular-nums"><?= Format::price($product->priceCents, $locale) ?></p>
        <div class="mt-4">
            <?php if ($product->stock > 0): ?>
                <app-add-to-cart product="<?= $product->id ?>"></app-add-to-cart>
            <?php else: ?>
                <p class="font-medium text-stone-500"><?= $this->t('Sold out for today') ?></p>
            <?php endif ?>
        </div>

        <h2 class="mt-8 text-lg font-semibold"><?= $this->t('Next pickup days') ?></h2>
        <ul class="mt-2 divide-y divide-stone-200 rounded-xl bg-white ring-1 ring-stone-200">
            <?php foreach ($model->days as $day): ?>
                <li class="flex items-center justify-between px-4 py-2">
                    <span class="font-medium"><?= Format::day($day, $locale) ?></span>
                    <?php if (isset($model->weather[$day])): $weather = $model->weather[$day] ?>
                        <span class="text-sm text-stone-600"><?= $sky($weather['code']) ?>, <?= round($weather['max']) ?> °C, <?= $this->t('{chance}% rain', ['chance' => $weather['rain']]) ?></span>
                    <?php endif ?>
                </li>
            <?php endforeach ?>
        </ul>
        <?php if ($model->weather === null): ?>
            <p class="mt-2 text-sm text-stone-500"><?= $this->t('The weather forecast is not available right now.') ?></p>
        <?php endif ?>
        <?php if (!$model->holidaysKnown): ?>
            <p class="mt-2 text-sm text-stone-500"><?= $this->t('We could not check the public holidays just now; on a holiday the shop is closed.') ?></p>
        <?php endif ?>
        <?php foreach ($model->closed as $date => $holiday): ?>
            <p class="mt-2 text-sm text-stone-600"><?= $this->t('Closed on {day}: {holiday}', ['day' => Format::day($date, $locale), 'holiday' => $holiday[$locale] ?? $holiday['en']]) |> htmlspecialchars(...) ?></p>
        <?php endforeach ?>

        <?php if ($this->env->isDev()): $t = $model->timings ?>
            <p class="mt-8 rounded-lg bg-stone-100 p-3 font-mono text-xs text-stone-600" data-timings>
                <?= $model->live ? sprintf(
                    'Live: loaded in %.0f ms with Tenma::all(): product %.0f ms, opening hours %.0f ms, holidays %.0f ms, weather %.0f ms. One after another: about %.0f ms.',
                    $t['total'], $t['product'], $t['hours'], $t['holidays'], $t['weather'],
                    $t['product'] + $t['hours'] + $t['holidays'] + $t['weather'],
                ) : sprintf(
                    'Cached: loaded in %.0f ms: product %.0f ms, opening hours %.0f ms, holidays and weather from memory. Add ?live to fetch them on this request.',
                    $t['total'], $t['product'], $t['hours'],
                ) ?>
            </p>
        <?php endif ?>
    </div>
</div>
