<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasVerifiedEmail();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'], 'description' => ['nullable', 'string', 'max:2000'], 'phone' => ['nullable', 'regex:/^\+[1-9][0-9]{6,14}$/'], 'location_name' => ['nullable', 'string', 'max:200'], 'address' => ['nullable', 'string', 'max:500'], 'timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
            'slug' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])$/', Rule::notIn(config('fitspot.reserved_slugs')), Rule::unique('workspaces', 'slug')->ignore($this->user()->workspace->id)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=10000,max_height=10000'], 'remove_photo' => ['boolean']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Введите телефон в международном формате, например +79991234567.'];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->has('photo') || ! $this->file('photo')) {
                return;
            }
            $size = getimagesize($this->file('photo')->getRealPath());
            if (! $size || $size[0] * $size[1] > 12000000) {
                $validator->errors()->add('photo', 'Изображение должно содержать не более 12 миллионов пикселей.');
            }
        }];
    }
}
