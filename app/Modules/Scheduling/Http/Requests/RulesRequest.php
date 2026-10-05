<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasVerifiedEmail();
    }

    public function rules(): array
    {
        return ['buffer_before' => ['required', 'integer', 'between:0,120'], 'buffer_after' => ['required', 'integer', 'between:0,120'], 'lead_minutes' => ['required', 'integer', 'between:0,10080'], 'horizon_days' => ['required', 'integer', 'between:1,90'], 'slot_step' => ['required', 'integer', Rule::in([5, 10, 15, 20, 30, 60])], 'cancel_minutes' => ['required', 'integer', 'between:0,10080'], 'reschedule_minutes' => ['required', 'integer', 'between:0,10080']];
    }

    public function after(): array
    {
        return [function ($v) {
            if ($v->errors()->isEmpty() && $this->integer('lead_minutes') >= $this->integer('horizon_days') * 1440) {
                $v->errors()->add('lead_minutes', 'Минимальное время до начала должно быть меньше горизонта записи.');
            }
        }];
    }
}
