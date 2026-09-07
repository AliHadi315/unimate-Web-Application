<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lecture extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
    ];

    /*  The frontend works in plain HH:MM, the column may carry seconds  */

    public function getStartTimeAttribute($value): string
    {
        return $value ? substr($value, 0, 5) : '';
    }

    public function getEndTimeAttribute($value): string
    {
        return $value ? substr($value, 0, 5) : '';
    }

    /*  Relationships  */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
