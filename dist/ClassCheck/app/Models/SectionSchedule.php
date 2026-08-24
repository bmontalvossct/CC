<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionSchedule extends Model
{
    protected $fillable = ['section_id', 'day_of_week', 'starts_at', 'ends_at', 'room', 'schedule_type'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
