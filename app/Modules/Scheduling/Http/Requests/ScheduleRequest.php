<?php

namespace App\Modules\Scheduling\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasVerifiedEmail();
    }

    public function rules(): array
    {
        return ['intervals' => ['present', 'array', 'max:56'], 'intervals.*.day' => ['required', 'integer', 'between:1,7'], 'intervals.*.start' => ['required', 'integer', 'between:0,1439'], 'intervals.*.end' => ['required', 'integer', 'between:1,1440']];
    }

    public function after(): array
    {
        return [function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $days = [];
            foreach ($this->input('intervals', []) as $i => $item) {
                if ($item['start'] >= $item['end']) {
                    $v->errors()->add("intervals.$i.end", 'Окончание должно быть позже начала.');
                }
                $days[$item['day']][] = $item;
            }
            foreach ($days as $items) {
                if (count($items) > 8) {
                    $v->errors()->add('intervals', 'Максимум 8 интервалов в день.');
                }
                usort($items, fn ($a, $b) => $a['start'] <=> $b['start']);
                $end = -1;
                foreach ($items as $item) {
                    if ($item['start'] < $end) {
                        $v->errors()->add('intervals', 'Рабочие интервалы не должны пересекаться.');
                    }
                    $end = max($end, $item['end']);
                }
            }
        }];
    }
}
