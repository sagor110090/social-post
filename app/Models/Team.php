<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function addMember(User $user, string $role = 'member'): void
    {
        $this->users()->attach($user, ['role' => $role]);
    }

    public function removeMember(User $user): void
    {
        $this->users()->detach($user);
    }

    public function hasMember(User $user): bool
    {
        return $this->users()->where('users.id', $user->id)->exists();
    }

    public function getMemberRole(User $user): ?string
    {
        $member = $this->users()->where('users.id', $user->id)->first();
        
        return $member?->pivot->role;
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function isAdmin(User $user): bool
    {
        return $this->getMemberRole($user) === 'admin';
    }

    public function isMember(User $user): bool
    {
        return $this->hasMember($user);
    }
}
