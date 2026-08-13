<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'academic_term_id', 'subject_code', 'subject_title', 'name',
        'room', 'enrollment_token', 'enrollment_open', 'archived_at',
    ];

    protected function casts(): array
    {
        return ['enrollment_open' => 'boolean', 'archived_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Section $section) {
            $section->enrollment_token ??= Str::random(48);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SectionSchedule::class);
    }

    public function layoutBlocks(): HasMany
    {
        return $this->hasMany(LayoutBlock::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function seats(): HasManyThrough
    {
        return $this->hasManyThrough(Seat::class, LayoutBlock::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function attendanceRecords(): HasManyThrough
    {
        return $this->hasManyThrough(AttendanceRecord::class, AttendanceSession::class);
    }
}
