@if ($this->cart->count() === 0)
    <p class="text-stone-600">{{ $this->i18n->t('Your cart is empty.') }} <a href="/" class="underline">{{ $this->i18n->t('Back to the shop') }}</a></p>
@else
    <ul class="divide-y divide-stone-200">
    @foreach ($this->cart->lines() as $line)
        <li data-key="{{ $line['product']->id }}" class="flex items-center gap-4 py-3">
            <img src="/products/{{ $line['product']->picture }}" alt="" class="size-12 rounded-lg bg-amber-50">
            <span class="flex-1 font-medium">{{ $line['product']->name($this->i18n->locale()) }}</span>
            <span class="inline-flex items-center gap-2">
                <button data-on-click="less($line['product'])" class="size-8 rounded-full ring-1 ring-stone-300" aria-label="{{ $this->i18n->t('One less') }}">−</button>
                <span class="w-6 text-center tabular-nums">{{ $line['quantity'] }}</span>
                <button data-on-click="more($line['product'])" class="size-8 rounded-full ring-1 ring-stone-300" aria-label="{{ $this->i18n->t('One more') }}">+</button>
            </span>
            <span class="w-20 text-right tabular-nums">{{ $this->price($line['product']->priceCents * $line['quantity']) }}</span>
            <button data-on-click="remove($line['product'])" class="text-sm text-stone-500 underline">{{ $this->i18n->t('Remove') }}</button>
        </li>
    @endforeach
    </ul>
    <p class="mt-4 text-right text-lg font-semibold">{{ $this->i18n->t('Total') }}: <span class="tabular-nums">{{ $this->price($this->cart->total()) }}</span></p>
@endif
