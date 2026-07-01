<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

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
    public function getAuthPassword()
{
    return $this->PasswordHash;
}
}
