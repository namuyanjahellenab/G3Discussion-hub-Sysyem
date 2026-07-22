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

    // CanResetPassword (pulled in via the base Authenticatable class) hardcodes
    // $this->email for both of these - lowercase, since stock Laravel expects a
    // snake_case column. This table's real column is Email, so without this
    // override the reset-link email/notification silently used null instead of
    // the user's actual address.
    public function getEmailForPasswordReset()
    {
        return $this->Email;
    }

    // Separate from getEmailForPasswordReset() above - this is what
    // Notifiable actually uses to address the "mail" channel for ANY
    // notification (not just password reset). Its default implementation
    // also hardcodes $this->email, so without this override every mail
    // notification (not only the reset link) would route to null and never
    // send, with no exception anywhere to explain why.
    public function routeNotificationForMail($notification = null)
    {
        return $this->Email;
    }

    // Stock Laravel's password-reset/update controllers (NewPasswordController,
    // Auth\PasswordController) write the new hash via forceFill/update(['password'
    // => ...]) - there's no "password" column here, only PasswordHash, so without
    // this mutator that crashed with "Unknown column 'password'" the moment
    // someone actually submitted a new password (the email/link itself sent fine;
    // this is a separate step). Routing it through $this->PasswordHash keeps the
    // existing 'hashed' cast's already-hashed-value detection intact, matching
    // every other place in this app that sets PasswordHash directly.
    public function setPasswordAttribute($value)
    {
        $this->PasswordHash = $value;
    }

}
