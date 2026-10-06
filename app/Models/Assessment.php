<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    public const TYPES = ['activity', 'laboratory', 'quiz', 'exam'];

    protected $fillable = [
        'section_id',
        'attendance_session_id',
        'type',
        'term_period',
        'assessment_number',
        'title',
        'description',
        'conducted_on',
        'max_points',
        'rubric_type',
        'rubric_data',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'activity_file_path',
        'activity_file_name',
        'activity_file_mime',
    ];

    protected function casts(): array
    {
        return [
            'conducted_on' => 'date',
            'max_points' => 'decimal:2',
            'rubric_data' => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function attendanceSession(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }
}
