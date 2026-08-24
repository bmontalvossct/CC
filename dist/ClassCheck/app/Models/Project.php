<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Project extends Model
{
    use HasFactory;

    public const TYPES = ['project', 'reporting', 'group_activity'];
    public const FORMATS = ['group', 'individual'];

    protected $fillable = [
        'section_id',
        'type',
        'format',
        'project_number',
        'title',
        'description',
        'conducted_on',
        'max_points',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
    ];

    protected function casts(): array
    {
        return [
            'conducted_on' => 'date',
            'max_points' => 'decimal:2',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(ProjectGroup::class)->orderBy('order_column')->orderBy('group_number');
    }

    public function members(): HasManyThrough
    {
        return $this->hasManyThrough(ProjectGroupMember::class, ProjectGroup::class);
    }
}
