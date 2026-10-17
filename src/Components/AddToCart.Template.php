<div class="flex flex-wrap items-center gap-3">
    <button data-on-click="add" class="rounded-lg bg-amber-800 px-4 py-2 font-medium text-white hover:bg-amber-700">
        {{ $this->i18n->t('Add to cart') }}
    </button>
    @if ($this->cart->quantity($this->product) > 0)
        <span class="text-sm text-stone-600">{{ $this->i18n->t('{count} in your cart', ['count' => $this->cart->quantity($this->product)]) }}</span>
    @endif
    @if ($this->notice !== null)
        <span role="status" class="text-sm text-red-700">{{ $this->notice }}</span>
    @endif
</div>
