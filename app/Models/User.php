<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'full_name',
        'university_id',
        'university_name',
        'country',
        'avatar_path',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /*  The shape the frontend expects for the logged-in user  */

    public function profile(): array
    {
        return [
            'id'              => $this->id,
            'fullName'        => $this->full_name,
            'universityId'    => $this->university_id,
            'universityName'  => $this->university_name,
            'country'         => $this->country,
            'avatarUrl'       => $this->avatar_path ? '/storage/'.$this->avatar_path : null,
        ];
    }

    /*  Relationships  */

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function lectures()
    {
        return $this->hasMany(Lecture::class);
    }

    public function resources()
    {
        return $this->hasMany(Resource::class);
    }

    public function chatSessions()
    {
        return $this->hasMany(ChatSession::class);
    }
}
