<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LayoutBlock extends Model
{
    protected $fillable = [
        'section_id',
        'label',
        'block_row',
        'block_column',
        'internal_rows',
        'internal_columns',
        'aisle_after_rows',
        'aisle_after_columns',
    ];

    protected function casts(): array
    {
        return [
            'aisle_after_rows' => 'array',
            'aisle_after_columns' => 'array',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }
}
