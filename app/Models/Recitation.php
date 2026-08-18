<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recitation extends Model
{
    protected $fillable = [
        'section_id',
        'student_id',
        'conducted_on',
        'accuracy',
        'delivery',
        'score',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'conducted_on' => 'date:Y-m-d',
            'score' => 'decimal:2',
            'accuracy' => 'integer',
            'delivery' => 'integer',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
