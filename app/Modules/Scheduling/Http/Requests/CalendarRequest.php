<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Modules\Scheduling\Services\IntervalValidator;
use Illuminate\Foundation\Http\FormRequest;

class CalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasVerifiedEmail();
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'min:1', 'max:31'], 'days.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'days.*.intervals' => ['present', 'array', 'max:8'],
            'days.*.intervals.*.start' => ['required', 'integer', 'between:0,1439'],
            'days.*.intervals.*.end' => ['required', 'integer', 'between:1,1440'],
        ];
    }

    public function after(): array
    {
        return [function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            foreach ($this->input('days', []) as $i => $day) {
                app(IntervalValidator::class)->validate($v, $day['intervals'], "days.$i.intervals", $day['date']);
            }
        }];
    }
}
