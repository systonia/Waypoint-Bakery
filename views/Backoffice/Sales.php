@model (Bakery\Models\SalesPage)

@section('title')
    {{ $this->t('Sales') }}
@endsection

<h1 class="mb-6 text-2xl font-semibold">
    {{ $this->t('Sales') }}
</h1>
<div class="grid gap-8 lg:grid-cols-2">
    <section>{{ $this->chart($model->revenue) }}</section>
    <section>{{ $this->chart($model->products) }}</section>
</div>
<section class="mt-10">
    <h2 class="mb-3 flex flex-wrap items-baseline gap-4 text-lg font-semibold">
        {{ $this->t('Orders of the last seven days') }}
        <a href="/backoffice/orders.xlsx" class="text-sm font-normal underline">{{ $this->t('90 days as XLSX') }}</a>
        <a href="/backoffice/orders.csv" class="text-sm font-normal underline">{{ $this->t('as CSV') }}</a>
    </h2>
    {{ $this->sheet($model->latest) }}
</section>
