@if ($this->cart->count() > 0)
<form data-on-submit="place" class="grid gap-4">
    <h2 class="text-xl font-semibold">{{ $this->i18n->t('Pickup') }}</h2>
    @if (isset($this->errors['cart']))
        <p role="alert" class="rounded-lg bg-red-50 p-3 text-red-800">{{ $this->errors['cart'] }}</p>
    @endif
    <label class="grid gap-1">
        <span class="text-sm font-medium">{{ $this->i18n->t('Your name') }}</span>
        <input name="name" value="{{ $this->form->name }}" autocomplete="name" class="rounded-lg border border-stone-300 px-3 py-2">
        @if (isset($this->errors['name']))
            <span class="text-sm text-red-700">{{ $this->errors['name'] }}</span>
        @endif
    </label>
    <label class="grid gap-1">
        <span class="text-sm font-medium">{{ $this->i18n->t('Email') }}</span>
        <input name="email" type="email" value="{{ $this->form->email }}" autocomplete="email" class="rounded-lg border border-stone-300 px-3 py-2">
        @if (isset($this->errors['email']))
            <span class="text-sm text-red-700">{{ $this->errors['email'] }}</span>
        @endif
    </label>
    <label class="grid gap-1">
        <span class="text-sm font-medium">{{ $this->i18n->t('Pickup day') }}</span>
        <select name="pickupDate" class="rounded-lg border border-stone-300 px-3 py-2">
            <option value="">{{ $this->i18n->t('Choose a day') }}</option>
            @foreach ($this->calendar->upcoming() as $date)
                <option value="{{ $date }}" @if ($this->form->pickupDate === $date) selected @endif>{{ Format::day($date, $this->i18n->locale()) }}</option>
            @endforeach
        </select>
        @if (isset($this->errors['pickupDate']))
            <span class="text-sm text-red-700">{{ $this->errors['pickupDate'] }}</span>
        @endif
    </label>
    <button class="rounded-lg bg-amber-800 px-4 py-3 font-semibold text-white hover:bg-amber-700">{{ $this->i18n->t('Reserve my order') }}</button>
    <p class="text-sm text-stone-500">{{ $this->i18n->t('You pay in the shop when you pick it up.') }}</p>
</form>
@endif
