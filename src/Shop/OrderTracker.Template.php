@code $order = $this->order() @endcode
@if ($order === null)
    <p>{{ $this->i18n->t('We cannot find this order.') }}</p>
@else
    <div class="grid gap-6">
        <p class="text-lg">
            {{ $this->i18n->t('Order {code} for {name}. Pickup: {day}', ['code' => $order->code, 'name' => $order->customerName, 'day' => Format::day($order->pickupDate, $this->i18n->locale())]) }}
        </p>
        @if ($order->status === OrderStatus::Pending)
            <div class="rounded-xl bg-amber-50 p-4 ring-1 ring-amber-200">
                <p class="font-medium">{{ $this->i18n->t('We have put your items aside until {time}. Please confirm the order to keep them.', ['time' => $this->reservedUntil($order)]) }}</p>
                <button data-on-click="confirm" class="mt-3 rounded-lg bg-amber-800 px-4 py-2 font-semibold text-white hover:bg-amber-700">{{ $this->i18n->t('Confirm order') }}</button>
            </div>
        @elseif ($order->status === OrderStatus::Cancelled)
            <p role="alert" class="rounded-xl bg-red-50 p-4 text-red-800">{{ $this->i18n->t('This order was cancelled because it was not confirmed in time.') }}</p>
        @else
            <ol class="grid gap-2 sm:grid-cols-4" aria-label="{{ $this->i18n->t('Status') }}">
            @foreach ($this->steps() as $step)
                <li data-key="{{ $step->value }}" class="{{ classes('rounded-xl p-3 text-center', $step === $order->status ? 'bg-amber-800 font-semibold text-white' : 'bg-stone-100 text-stone-500') }}" @if ($step === $order->status) aria-current="step" @endif>
                    {{ $step->label($this->i18n) }}
                </li>
            @endforeach
            </ol>
        @endif
        <table class="w-full text-left">
            <tbody class="divide-y divide-stone-200">
            @foreach ($order->lines as $line)
                <tr data-key="{{ $line->product }}"><td class="py-2">{{ $line->quantity }} × {{ $line->name }}</td><td class="py-2 text-right tabular-nums">{{ Format::price($line->totalCents(), $this->i18n->locale()) }}</td></tr>
            @endforeach
            </tbody>
            <tfoot><tr><th class="py-2">{{ $this->i18n->t('Total') }}</th><td class="py-2 text-right font-semibold tabular-nums">{{ Format::price($order->totalCents(), $this->i18n->locale()) }}</td></tr></tfoot>
        </table>
    </div>
@endif
