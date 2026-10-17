@section('title'){{ $this->t('Cart') }}@endsection
<h1 class="text-3xl font-semibold">{{ $this->t('Your cart') }}</h1>
<div class="mt-6 grid gap-10 md:grid-cols-[3fr_2fr]">
    <section class="rounded-2xl bg-white p-6 ring-1 ring-stone-200"><app-cart-editor></app-cart-editor></section>
    <section><app-checkout></app-checkout></section>
</div>
