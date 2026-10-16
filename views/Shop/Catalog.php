<?php
/** @var array{products: list<Bakery\Catalog\Product>} $model */
use Bakery\Format;

$locale = $this->locale();
?>
<?php $this->startSection('title') ?><?= $this->t('Shop') ?><?php $this->endSection() ?>
<h1 class="text-3xl font-semibold"><?= $this->t('Baked this morning') ?></h1>
<p class="mt-2 text-stone-600"><?= $this->t('Put together your order and pick it up in the shop on a day that suits you.') ?></p>
<ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
<?php foreach ($model['products'] as $product): ?>
    <li class="flex flex-col rounded-2xl bg-white p-4 shadow-sm ring-1 ring-stone-200">
        <a href="/products/<?= $product->slug ?>" class="block">
            <img src="/products/<?= $product->picture ?>" alt="" class="aspect-[4/3] w-full rounded-xl bg-amber-50 object-contain p-4">
            <h2 class="mt-3 text-lg font-semibold"><?= $product->name($locale) |> htmlspecialchars(...) ?></h2>
        </a>
        <p class="mt-1 flex-1 text-sm text-stone-600"><?= $product->description($locale) |> htmlspecialchars(...) ?></p>
        <p class="mt-3 flex items-baseline justify-between">
            <span class="text-lg font-semibold tabular-nums"><?= Format::price($product->priceCents, $locale) ?></span>
            <span class="text-sm text-stone-500"><?= $this->t('{count} left', ['count' => $product->stock]) ?></span>
        </p>
        <div class="mt-3">
            <?php if ($product->stock > 0): ?>
                <app-add-to-cart product="<?= $product->id ?>"></app-add-to-cart>
            <?php else: ?>
                <p class="font-medium text-stone-500"><?= $this->t('Sold out for today') ?></p>
            <?php endif ?>
        </div>
    </li>
<?php endforeach ?>
</ul>
