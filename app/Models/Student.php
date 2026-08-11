<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id', 'student_number', 'first_name', 'middle_name', 'last_name', 'photo_path', 'is_active',
    ];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->join(' ');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function seat(): HasOne
    {
        return $this->hasOne(Seat::class);
    }
}
