<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasVerifiedEmail();
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:2000'], 'type' => ['required', Rule::in(['personal', 'split'])], 'format' => ['required', Rule::in(['in_person', 'online'])], 'duration' => ['required', 'integer', 'between:15,240', 'multiple_of:5'], 'price' => ['required', 'string', 'regex:/^\d{1,7}(?:[.,]\d{1,2})?$/'], 'capacity' => ['required', 'integer', Rule::in([$this->input('type') === 'split' ? 2 : 1])], 'location' => ['nullable', 'string', 'max:500'], 'active' => ['required', 'boolean']];
    }

    public function after(): array
    {
        return [function ($v) {
            if ($v->errors()->has('price')) {
                return;
            }
            if ($this->kopecks() > 100000000) {
                $v->errors()->add('price', 'Максимальная цена — 1 000 000 ₽.');
            }
        }];
    }

    public function serviceData(): array
    {
        $data = $this->safe()->except('price');
        $data['price_kopecks'] = $this->kopecks();
        if ($data['format'] === 'online') {
            $data['location'] = null;
        }

        return $data;
    }

    private function kopecks(): int
    {
        $parts = explode('.', str_replace(',', '.', $this->input('price')));

        return ((int) $parts[0]) * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }
}
