<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $table = 'membership';
    protected $fillable = [
        'membership_name',
        'description',
        'tasks_per_day',
        'max_tasks',
        'task_link',
        'userid',
        'benefits',
        'price',
        'duration_days',
    ];

   public function user()
    {
        return $this->belongsTo(User::class, 'userid', 'id');
    }
}
