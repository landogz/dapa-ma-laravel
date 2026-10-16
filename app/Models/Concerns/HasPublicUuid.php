<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

trait HasPublicUuid
{
    protected static function bootHasPublicUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null && $field !== $this->getKeyName()) {
            return parent::resolveRouteBinding($value, $field);
        }

        return static::findByPublicKey((string) $value);
    }

    public static function findByPublicKey(string $key): static
    {
        if (Str::isUuid($key)) {
            return static::query()->where('uuid', $key)->firstOrFail();
        }

        if (! ctype_digit($key)) {
            throw (new ModelNotFoundException)->setModel(static::class);
        }

        return static::query()->findOrFail((int) $key);
    }
}
