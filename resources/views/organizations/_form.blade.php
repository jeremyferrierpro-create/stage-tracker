@php($organization = $organization ?? null)

<label for="name">Nom</label>
<input id="name" name="name" type="text" maxlength="160"
       value="{{ old('name', $organization?->name) }}" required>
@error('name') <p class="error">{{ $message }}</p> @enderror

<label for="type">Type</label>
<select id="type" name="type" required>
    @foreach (\App\Domain\Organization\Models\Organization::TYPES as $type)
        <option value="{{ $type }}" @selected(old('type', $organization?->type) === $type)>
            {{ \App\Domain\Organization\Models\Organization::typeLabelFor($type) }}
        </option>
    @endforeach
</select>
@error('type') <p class="error">{{ $message }}</p> @enderror

<label for="contact_email">E-mail de contact</label>
<input id="contact_email" name="contact_email" type="email" maxlength="190"
       value="{{ old('contact_email', $organization?->contact_email) }}">
@error('contact_email') <p class="error">{{ $message }}</p> @enderror

<label for="contact_phone">Téléphone</label>
<input id="contact_phone" name="contact_phone" type="text" maxlength="40"
       value="{{ old('contact_phone', $organization?->contact_phone) }}">
@error('contact_phone') <p class="error">{{ $message }}</p> @enderror

<label for="address">Adresse</label>
<textarea id="address" name="address" maxlength="2000">{{ old('address', $organization?->address) }}</textarea>
@error('address') <p class="error">{{ $message }}</p> @enderror
