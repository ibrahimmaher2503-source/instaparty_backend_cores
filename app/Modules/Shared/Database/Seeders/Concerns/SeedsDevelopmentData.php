<?php

declare(strict_types=1);

namespace App\Modules\Shared\Database\Seeders\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait SeedsDevelopmentData
{
    protected function stablePublicId(string $seed): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $hash = hash('sha256', $seed, true);
        $publicId = $alphabet[ord($hash[0]) % 8];

        for ($index = 1; $index < 26; $index++) {
            $publicId .= $alphabet[ord($hash[$index]) % strlen($alphabet)];
        }

        return $publicId;
    }

    /**
     * @param  array<string, mixed>  $keys
     */
    protected function updateOrCreateFactoryModel(Model $model, array $keys): Model
    {
        $existing = $this->findExistingFactoryModel($model, $keys);
        $attributes = array_merge($this->factoryAttributes($model), $keys);

        if ($existing !== null) {
            unset($attributes['id'], $attributes['created_at']);

            if (
                ! array_key_exists('public_id', $attributes)
                || Str::isUlid((string) $existing->getAttribute('public_id'))
                || ! Str::isUlid((string) $attributes['public_id'])
            ) {
                unset($attributes['public_id']);
            }

            $existing->fill($attributes);
            $existing->save();

            return $existing->refresh();
        }

        return $model->newQuery()->create($attributes);
    }

    /**
     * Append-only friendly factory persistence. Existing rows are never updated.
     *
     * @param  array<string, mixed>  $keys
     */
    protected function firstOrCreateFactoryModel(Model $model, array $keys): Model
    {
        $existing = $this->findExistingFactoryModel($model, $keys);

        if ($existing !== null) {
            return $existing;
        }

        return $model->newQuery()->create(array_merge($this->factoryAttributes($model), $keys));
    }

    /**
     * @return array<string, mixed>
     */
    protected function factoryAttributes(Model $model): array
    {
        $attributes = Arr::except($model->getAttributes(), ['id']);
        $casts = $model->getCasts();
        $translatable = method_exists($model, 'getTranslatableAttributes')
            ? $model->getTranslatableAttributes()
            : ($model->translatable ?? []);

        foreach ($attributes as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            $cast = (string) ($casts[$key] ?? '');
            $isJsonLike = in_array($key, $translatable, true)
                || in_array($cast, ['array', 'json', 'object', 'collection'], true)
                || str_contains($cast, ':array');

            if ($isJsonLike) {
                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $attributes[$key] = $decoded;
                }
            }
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $keys
     */
    private function findExistingFactoryModel(Model $model, array $keys): ?Model
    {
        /** @var Builder<Model> $query */
        $query = $model->newQuery();

        foreach ($keys as $column => $value) {
            $value === null
                ? $query->whereNull($column)
                : $query->where($column, $value);
        }

        return $query->first();
    }

    /** @param array<string, mixed> $keys */
    protected function lookupId(string $table, array $keys): ?int
    {
        $query = DB::table($table);

        foreach ($keys as $column => $value) {
            $value === null
                ? $query->whereNull($column)
                : $query->where($column, $value);
        }

        $id = $query->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    protected function upsertRow(
        string $table,
        array $keys,
        array $values,
        bool $hasPublicId = false,
        bool $hasUpdatedAt = true,
    ): int {
        $id = $this->lookupId($table, $keys);
        $now = now();

        if ($id !== null) {
            $updates = $values;

            if ($hasUpdatedAt) {
                $updates['updated_at'] = $now;
            }

            if ($updates !== []) {
                DB::table($table)->where('id', $id)->update($updates);
            }

            return $id;
        }

        $insert = array_merge($keys, $values);

        if ($hasPublicId && ! array_key_exists('public_id', $insert)) {
            $insert['public_id'] = (string) Str::ulid();
        }

        if ($hasUpdatedAt) {
            $insert['created_at'] ??= $now;
            $insert['updated_at'] ??= $now;
        }

        return (int) DB::table($table)->insertGetId($insert);
    }

    /**
     * Append-only friendly first-or-insert. Existing rows are never updated.
     *
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    protected function firstOrInsertRow(
        string $table,
        array $keys,
        array $values,
        bool $hasPublicId = false,
    ): int {
        $id = $this->lookupId($table, $keys);

        if ($id !== null) {
            return $id;
        }

        $insert = array_merge($keys, $values);

        if ($hasPublicId && ! array_key_exists('public_id', $insert)) {
            $insert['public_id'] = (string) Str::ulid();
        }

        $insert['created_at'] ??= now();

        return (int) DB::table($table)->insertGetId($insert);
    }

    /** @param array<string, mixed> $value */
    protected function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    protected function updateOrInsertPivot(string $table, array $keys, array $values = []): void
    {
        DB::table($table)->updateOrInsert($keys, $values);
    }
}
