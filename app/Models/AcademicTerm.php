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
     * Resolve a teacher's term before the section transaction begins.
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

        $academicTerm = static::query()->firstOrCreate($identity, [
            'starts_on' => $term['starts_on'],
            'ends_on' => $term['ends_on'],
        ]);

        static::query()->whereKey($academicTerm->getKey())->update([
            'starts_on' => $term['starts_on'],
            'ends_on' => $term['ends_on'],
        ]);

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
