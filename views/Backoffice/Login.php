@model (Bakery\Requests\LoginForm)

@code
    $errors = $this->request?->method === 'POST' ? $model->errors : []
@endcode

@section('title')
    {{ $this->t('Sign in') }}
@endsection

<form method="post" action="/backoffice/login" class="mx-auto grid max-w-sm gap-4">
    {{ $this->csrf->field() }}
    <h1 class="text-2xl font-semibold">
        {{ $this->t('Sign in') }}
    </h1>
    <label class="grid gap-1">
        <span class="text-sm font-medium">{{ $this->t('Email') }}</span>
        <input type="email" name="email" value="{{ $model->email }}" required autocomplete="username" class="rounded-lg border border-stone-300 px-3 py-2">
        @if (isset($errors['email']))
            <span class="text-sm text-red-700">{{ $errors['email'] }}</span>
        @endif
    </label>
    <label class="grid gap-1">
        <span class="text-sm font-medium">{{ $this->t('Password') }}</span>
        <input type="password" name="password" required autocomplete="current-password" class="rounded-lg border border-stone-300 px-3 py-2">
        @if (isset($errors['password']))
            <span class="text-sm text-red-700">{{ $errors['password'] }}</span>
        @endif
    </label>
    <button class="rounded-lg bg-stone-900 px-4 py-2 font-semibold text-white">{{ $this->t('Sign in') }}</button>
    <p class="text-sm text-stone-500">
        {{ $this->t('Demo: anna@sonnenkorn.test / croissant (owner), ben@sonnenkorn.test / pretzel (staff).') }}
    </p>
</form>
