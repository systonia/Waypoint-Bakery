@use (Bakery\Services\Format)
@model (Bakery\Models\ProductPage)

@code
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
@endcode

@section('title')
    {{ $product->name($locale) }}
@endsection

<a href="/" class="text-sm text-stone-500 underline">{{ $this->t('All products') }}</a>
<div class="mt-4 grid gap-8 md:grid-cols-2">
    <img src="/products/{{ $product->picture }}" alt="" class="w-full rounded-2xl bg-amber-50 p-8">
    <div>
        <h1 class="text-3xl font-semibold">
            {{ $product->name($locale) }}
        </h1>
        <p class="mt-3 text-stone-700">
            {{ $product->description($locale) }}
        </p>
        <p class="mt-4 text-2xl font-semibold tabular-nums">{{ Format::price($product->priceCents, $locale) }}</p>
        <div class="mt-4">
            @if ($product->stock > 0)
                <app-add-to-cart product="{{ $product->id }}"></app-add-to-cart>
            @else
                <p class="font-medium text-stone-500">{{ $this->t('Sold out for today') }}</p>
            @endif
        </div>

        <h2 class="mt-8 text-lg font-semibold">
            {{ $this->t('Next pickup days') }}
        </h2>
        <ul class="mt-2 divide-y divide-stone-200 rounded-xl bg-white ring-1 ring-stone-200">
            @foreach ($model->days as $day)
                <li class="flex items-center justify-between px-4 py-2">
                    <span class="font-medium">{{ Format::day($day, $locale) }}</span>
                    @if (isset($model->weather[$day]))
                        @code
                            $weather = $model->weather[$day]
                        @endcode
                        <span class="text-sm text-stone-600">{{ $sky($weather->code) }}, {{ round($weather->max) }} °C, {{ $this->t('{chance}% rain', ['chance' => $weather->rain]) }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($model->weather === null)
            <p class="mt-2 text-sm text-stone-500">
                {{ $this->t('The weather forecast is not available right now.') }}
            </p>
        @endif
        @if (!$model->holidaysKnown)
            <p class="mt-2 text-sm text-stone-500">
                {{ $this->t('We could not check the public holidays just now; on a holiday the shop is closed.') }}
            </p>
        @endif
        @foreach ($model->closed as $date => $holiday)
            <p class="mt-2 text-sm text-stone-600">
                {{ $this->t('Closed on {day}: {holiday}', ['day' => Format::day($date, $locale), 'holiday' => $holiday->name($locale)]) }}
            </p>
        @endforeach
    </div>
</div>
