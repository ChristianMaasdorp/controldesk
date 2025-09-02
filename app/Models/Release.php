<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Release extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'project_id',
        'release_date',
        'is_released',
        'notes'
    ];

    protected $casts = [
        'release_date' => 'datetime',
        'is_released' => 'boolean',
    ];

    /**
     * Get the project that owns the release.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the tickets associated with this release.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Scope to get only released releases.
     */
    public function scopeReleased($query)
    {
        return $query->where('is_released', true);
    }

    /**
     * Scope to get only unreleased releases.
     */
    public function scopeUnreleased($query)
    {
        return $query->where('is_released', false);
    }

    /**
     * Scope to get releases by project.
     */
    public function scopeByProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }
}
