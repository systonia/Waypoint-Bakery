<?php /** @var Bakery\Ordering\Order $model */ ?>
<?php $this->startSection('title') ?><?= $this->t('Your order') ?><?php $this->endSection() ?>
<h1 class="text-3xl font-semibold"><?= $this->t('Your order') ?></h1>
<p class="mt-2 text-sm text-stone-500"><?= $this->t('Keep this page open: it shows each step the moment the bakery takes it.') ?></p>
<section class="mt-6 rounded-2xl bg-white p-6 ring-1 ring-stone-200">
    <app-order-tracker code="<?= $model->code ?>"></app-order-tracker>
</section>
