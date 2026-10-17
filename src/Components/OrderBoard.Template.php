<div class="grid gap-4 lg:grid-cols-4">
    @foreach ($this->columns() as [$status, $orders])
        <section data-key="{{ $status->value }}" class="rounded-2xl bg-stone-100 p-3">
            <h2 class="mb-3 flex justify-between font-semibold">
                {{ $status->label($this->i18n) }}
                <span class="rounded-full bg-white px-2 text-sm">{{ count($orders) }}</span>
            </h2>
            <ul class="grid gap-3">
                @foreach ($orders as $order)
                    <li data-key="{{ $order->code }}" class="rounded-xl bg-white p-3 shadow-sm">
                        <p class="flex justify-between text-sm text-stone-500">
                            <span>{{ $order->code }}</span>
                            <span>{{ Format::day($order->pickupDate, $this->i18n->locale()) }}</span>
                        </p>
                        <p class="font-medium">{{ $order->customerName }}</p>
                        <ul class="mt-1 text-sm">
                            @foreach ($order->lines as $line)
                                <li>{{ $line->quantity }} × {{ $line->name }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-1 text-sm tabular-nums">{{ Format::price($order->totalCents(), $this->i18n->locale()) }}</p>
                        @if ($order->status->next() !== null)
                            <button data-on-click="advance($order)" class="mt-2 w-full rounded-lg bg-amber-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700">
                                → {{ $order->status->next()->label($this->i18n) }}
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>
