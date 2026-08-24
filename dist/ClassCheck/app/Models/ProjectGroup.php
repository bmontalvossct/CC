<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'group_number',
        'name',
        'topic',
        'description',
        'score',
        'notes',
        'order_column',
    ];

    protected function casts(): array
    {
        return [
            'group_number' => 'integer',
            'order_column' => 'integer',
            'score' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectGroupMember::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'project_group_members')
            ->withPivot(['id', 'role', 'score', 'notes'])
            ->withTimestamps();
    }
}
