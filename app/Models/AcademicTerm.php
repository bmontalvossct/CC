<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'school_year',
        'starts_on',
        'ends_on',
        'is_current',
        'default_starts_at',
        'default_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * Get or create the universal active academic term for the teacher.
     */
    public static function currentForUser(int $userId): self
    {
        $current = static::query()
            ->where('user_id', $userId)
            ->where('is_current', true)
            ->first();

        if ($current) {
            return $current;
        }

        $latest = static::query()
            ->where('user_id', $userId)
            ->latest('updated_at')
            ->first();

        if ($latest) {
            $latest->makeCurrent();

            return $latest;
        }

        $now = now();
        $startYear = $now->month >= 6 ? $now->year : $now->year - 1;
        $schoolYear = "{$startYear}-" . ($startYear + 1);

        $defaultTerm = static::create([
            'user_id' => $userId,
            'name' => '1st Semester',
            'school_year' => $schoolYear,
            'starts_on' => "{$startYear}-08-01",
            'ends_on' => "{$startYear}-12-15",
            'is_current' => true,
            'default_starts_at' => '08:00',
            'default_ends_at' => '09:30',
        ]);

        return $defaultTerm;
    }

    /**
     * Mark this academic term as the current active term for its user.
     */
    public function makeCurrent(): self
    {
        static::query()
            ->where('user_id', $this->user_id)
            ->whereKeyNot($this->getKey())
            ->update(['is_current' => false]);

        $this->update(['is_current' => true]);

        return $this->refresh();
    }

    /**
     * Resolve a teacher's term before the section transaction begins.
     *
     * @param  array{name: string, school_year: string, starts_on?: string, ends_on?: string, is_current?: bool, default_starts_at?: string, default_ends_at?: string}  $term
     */
    public static function resolveForUser(int $userId, array $term): self
    {
        $identity = [
            'user_id' => $userId,
            'name' => $term['name'],
            'school_year' => $term['school_year'],
        ];

        $current = static::currentForUser($userId);

        $academicTerm = static::query()->firstOrCreate($identity, [
            'starts_on' => $term['starts_on'] ?? $current->starts_on->format('Y-m-d'),
            'ends_on' => $term['ends_on'] ?? $current->ends_on->format('Y-m-d'),
            'is_current' => true,
            'default_starts_at' => $term['default_starts_at'] ?? $current->default_starts_at ?? '08:00',
            'default_ends_at' => $term['default_ends_at'] ?? $current->default_ends_at ?? '09:30',
        ]);

        $updates = [];
        if (! empty($term['starts_on'])) {
            $updates['starts_on'] = $term['starts_on'];
        }
        if (! empty($term['ends_on'])) {
            $updates['ends_on'] = $term['ends_on'];
        }
        if (isset($term['default_starts_at'])) {
            $updates['default_starts_at'] = $term['default_starts_at'];
        }
        if (isset($term['default_ends_at'])) {
            $updates['default_ends_at'] = $term['default_ends_at'];
        }

        if (! empty($updates)) {
            static::query()->whereKey($academicTerm->getKey())->update($updates);
        }

        if (! empty($term['is_current'])) {
            $academicTerm->makeCurrent();
        }

        return $academicTerm->refresh();
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
