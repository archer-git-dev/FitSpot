<?php

namespace App\Modules\Scheduling\Http\Requests;

use App\Modules\Scheduling\Services\IntervalValidator;
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
            $groups = [];
            foreach ($this->input('intervals', []) as $i => $item) {
                $groups[$item['day']][$i] = $item;
            }
            $labels = [1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота', 7 => 'Воскресенье'];
            foreach ($groups as $day => $items) {
                app(IntervalValidator::class)->validate($v, $items, 'intervals', $labels[$day]);
            }
            if ($v->errors()->isNotEmpty()) {
                $v->errors()->add('intervals', 'График не сохранён — исправьте отмеченные интервалы.');
            }
        }];
    }
}
