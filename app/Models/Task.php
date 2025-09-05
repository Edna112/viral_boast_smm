<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'task';
    protected $fillable = [
        'task_name',
        'task_type',
        'task_url',
        'user_id',
        'membership_id',
        'status',
        'duration',
    ];

    protected $casts = [
        'duration' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }
}
