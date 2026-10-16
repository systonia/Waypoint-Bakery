<a href="/cart" class="inline-flex items-center gap-2 rounded-full bg-amber-100 px-4 py-2 font-medium text-amber-900 hover:bg-amber-200">
    {{ $this->i18n->t('Cart') }}
    <span class="rounded-full bg-amber-900 px-2 text-sm text-amber-50">{{ $this->cart->count() }}</span>
</a>
