<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'school_year', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    /**
     * Resolve a teacher's term without firstOrCreate's nested savepoint.
     *
     * Neon uses PostgreSQL transaction pooling in production. A single upsert is
     * both concurrency-safe and avoids opening a nested transaction while a
     * section is being created or updated.
     *
     * @param  array{name: string, school_year: string, starts_on: string, ends_on: string}  $term
     */
    public static function resolveForUser(int $userId, array $term): self
    {
        $identity = [
            'user_id' => $userId,
            'name' => $term['name'],
            'school_year' => $term['school_year'],
        ];

        static::query()->upsert(
            [[...$identity, 'starts_on' => $term['starts_on'], 'ends_on' => $term['ends_on']]],
            ['user_id', 'name', 'school_year'],
            ['starts_on', 'ends_on'],
        );

        return static::query()->where($identity)->firstOrFail();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
