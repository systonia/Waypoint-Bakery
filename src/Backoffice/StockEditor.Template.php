<table class="w-full text-left">
    <thead class="text-sm text-stone-500"><tr><th class="py-2">{{ $this->i18n->t('Product') }}</th><th class="py-2 text-right">{{ $this->i18n->t('In stock') }}</th></tr></thead>
    <tbody class="divide-y divide-stone-200">
    @foreach ($this->products->all() as $product)
        <tr data-key="{{ $product->id }}">
            <td class="py-2">{{ $product->name($this->i18n->locale()) }}</td>
            <td class="py-2 text-right">
                <input type="number" min="0" value="{{ $product->stock }}" data-on-change="set($product)" aria-label="{{ $product->name($this->i18n->locale()) }}" class="w-24 rounded-lg border border-stone-300 px-2 py-1 text-right">
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
@if ($this->saved !== null)
    <p role="status" class="mt-3 text-sm text-green-800">{{ $this->saved }}</p>
@endif
