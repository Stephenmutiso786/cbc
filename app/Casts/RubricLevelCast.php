<?php

namespace App\Casts;

use App\Enums\RubricLevel;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class RubricLevelCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?RubricLevel
    {
        if ($value instanceof RubricLevel || $value === null || $value === '') {
            return $value instanceof RubricLevel ? $value : null;
        }

        $code = strtoupper(trim((string) $value));

        return RubricLevel::tryFrom($code)
            ?? RubricLevel::tryFrom(substr($code, 0, 2));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value instanceof RubricLevel) {
            return [$key => $value->value];
        }

        if ($value === null || $value === '') {
            return [$key => null];
        }

        $code = strtoupper(trim((string) $value));
        $rubric = RubricLevel::tryFrom($code)
            ?? RubricLevel::tryFrom(substr($code, 0, 2));

        return [$key => $rubric?->value];
    }
}
