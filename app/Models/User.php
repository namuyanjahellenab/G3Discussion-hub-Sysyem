<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Group;
use App\Models\GroupStudent;

class User extends Authenticatable
{
    protected $table = 'User';
    protected $primaryKey = 'UserID';
    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

/** @use HasFactory<UserFactory> */
   use HasFactory, Notifiable, HasApiTokens;
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    // 
    protected $fillable = [
    'UserName',
    'Handle',
    'Email',
    'PasswordHash',
    'Role',
    'Status',
    'RulesAccepted',
    'LastActive',
    'ThemeColor',
    'DefaultQuizDurationMinutes',
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

   public function groups()
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

    /**
     * Called whenever a user posts/replies/messages. Refreshes LastActive,
     * flips a stale 'Inactive' Status back to 'Active' immediately rather
     * than waiting for the next scheduled sweep, and clears any in-progress
     * inactivity warning stage since they've now responded.
     */
    public static function recordActivity(int $userId): void
    {
        $user = self::find($userId);

        if (!$user) {
            return;
        }

        $user->update([
            'LastActive' => now(),
            'Status' => $user->Status === 'Inactive' ? 'Active' : $user->Status,
        ]);

        UserInactivityWarning::where('UserID', $userId)->delete();
    }

}
