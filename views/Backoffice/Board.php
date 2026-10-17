@section('title')
    {{ $this->t('Orders') }}
@endsection

<h1 class="mb-6 text-2xl font-semibold">
    {{ $this->t('Open orders') }}
</h1>
<app-order-board></app-order-board>
