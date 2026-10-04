{{--
    International phone input: country-code select + number.
    Params: $id (input id prefix), $label, $required (bool),
            $country (preselected region), $number (prefilled number), $help (optional).
--}}
@inject('phoneNumbers', 'App\Services\PhoneNumberService')
@php
    $selectedCountry = old('phone_country', $country ?? null) ?: $phoneNumbers->defaultRegion();
    $required = $required ?? true;
@endphp
<div class="mb-3">
    <label for="{{ $id }}" class="booking-label">{{ $label ?? 'Phone Number' }}{{ $required ? ' *' : '' }}</label>
    <div class="phone-input-group">
        <select id="{{ $id }}-country" name="phone_country"
                class="form-select phone-country-select @error('phone_country') is-invalid @enderror"
                aria-label="Country code" autocomplete="tel-country-code">
            @foreach($phoneNumbers->countryOptions() as $region => $countryLabel)
                <option value="{{ $region }}" @selected($selectedCountry === $region)>{{ $countryLabel }}</option>
            @endforeach
        </select>
        <input id="{{ $id }}" type="tel" name="phone" inputmode="tel" autocomplete="tel-national" maxlength="30"
               class="form-control @error('phone') is-invalid @enderror"
               value="{{ old('phone', $number ?? '') }}" placeholder="98XXXXXXXX"
               @if($help ?? null) aria-describedby="{{ $id }}-help" @endif @if($required) required @endif>
    </div>
    @error('phone')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('phone_country')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @if($help ?? null)
    <div id="{{ $id }}-help" class="form-text">{{ $help }}</div>
    @endif
</div>
