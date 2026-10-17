{{/* The back office's frame: its navigation, the signed-in person (the request's JWT), the flash messages. */}}

@code
    $user = $this->request?->jwt
@endcode

<!doctype html>
<html lang="{{ $this->locale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Sonnenkorn {{ $this->t('Back office') }}</title>
    <link rel="stylesheet" href="/app.css">
    <link rel="icon" href="/favicon.svg">
</head>
<body class="min-h-screen bg-white text-stone-900">
{{ $this->flashes() }}
<header class="bg-stone-900 text-stone-100">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-6 px-4 py-3">
        <a href="/backoffice" class="font-semibold">Sonnenkorn · {{ $this->t('Back office') }}</a>
        @if ($user !== null)
            <nav class="flex gap-4 text-sm">
                <a href="/backoffice">{{ $this->t('Orders') }}</a>
                <a href="/backoffice/stock">{{ $this->t('Stock') }}</a>
                @if ($user['role'] === 'owner')
                    <a href="/backoffice/sales">{{ $this->t('Sales') }}</a>
                @endif
                <a href="/openapi/swagger.html">{{ $this->t('API') }}</a>
            </nav>
        @endif
        <span class="ml-auto flex items-center gap-3 text-sm">
            <a href="?lang=en">EN</a> <a href="?lang=de">DE</a>
            <a href="/" class="text-stone-400">{{ $this->t('To the shop') }}</a>
            @if ($user !== null)
                <form method="post" action="/backoffice/logout">
                    {{ $this->csrf->field() }}
                    <button class="rounded bg-stone-700 px-2 py-1">{{ $this->t('Sign out {name}', ['name' => $user['name']]) }}</button>
                </form>
            @endif
        </span>
    </div>
</header>
<main class="mx-auto max-w-7xl px-4 py-8">{{ $content }}</main>
{{ $this->waypointJsTag() }}
{{ $this->pluginAssetTags() }}
</body>
</html>
