<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seat extends Model
{
    protected $fillable = [
        'layout_block_id', 'student_id', 'row_number', 'column_number', 'label', 'is_disabled',
    ];

    protected function casts(): array
    {
        return ['is_disabled' => 'boolean'];
    }

    public function layoutBlock(): BelongsTo
    {
        return $this->belongsTo(LayoutBlock::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
