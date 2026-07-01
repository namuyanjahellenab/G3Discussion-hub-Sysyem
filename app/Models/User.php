<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Group;
use App\Models\GroupStudent;

class User extends Authenticatable
{
    protected $table = 'User';
    protected $primaryKey = 'UserID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

/** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
    'UserName',
    'Email',
    'PasswordHash',
    'Role',
    'Status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'PasswordHash',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active' => 'datetime',
            'PasswordHash' => 'hashed',
            'rules_accepted' => 'boolean',
        ];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'GroupStudent', 'UserID', 'GroupID', 'UserID', 'GroupID')
            ->withPivot(['StudentID', 'Status', 'CreatedAt', 'UpdatedAt']);
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupStudent::class, 'UserID', 'UserID');
    }

    public function getAuthPassword()
    {
        return $this->PasswordHash;
    }
}
