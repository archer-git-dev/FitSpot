<?php

namespace App\Modules\Scheduling\Services;

use Illuminate\Validation\Validator;

class IntervalValidator
{
    public function validate(Validator $validator, array $items, string $prefix, string $label): void
    {
        if (count($items) > 8) {
            $validator->errors()->add($prefix, "$label: максимум 8 интервалов.");
        }
        $sorted = [];
        foreach ($items as $index => $item) {
            if (! isset($item['start'], $item['end']) || ! is_numeric($item['start']) || ! is_numeric($item['end'])) {
                continue;
            }
            if ($item['start'] >= $item['end']) {
                $validator->errors()->add("$prefix.$index.end", 'Окончание должно быть позже начала.');
            }
            $sorted[] = ['index' => $index, 'start' => $item['start'], 'end' => $item['end']];
        }
        usort($sorted, fn ($a, $b) => $a['start'] <=> $b['start']);
        // Mark every pair, including a long interval containing several shorter ones.
        foreach ($sorted as $i => $a) {
            foreach (array_slice($sorted, $i + 1) as $b) {
                if ($b['start'] >= $a['end']) {
                    break;
                }
                if ($a['start'] < $b['end']) {
                    foreach ([$a, $b] as $item) {
                        $validator->errors()->add("$prefix.{$item['index']}.start", "$label: рабочие интервалы не должны пересекаться.");
                    }
                }
            }
        }
    }

    public function merge(array $items): array
    {
        usort($items, fn ($a, $b) => $a['start'] <=> $b['start']);
        $merged = [];
        foreach ($items as $item) {
            $item = ['start' => (int) $item['start'], 'end' => (int) $item['end']];
            $last = count($merged) - 1;
            if ($last >= 0 && $merged[$last]['end'] === $item['start']) {
                $merged[$last]['end'] = $item['end'];
            } else {
                $merged[] = $item;
            }
        }

        return $merged;
    }
}
