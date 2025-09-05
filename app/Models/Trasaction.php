<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Trasaction extends Model
{
    protected $table = 'transaction';
    protected $fillable = [
        'user_id',
        'amount',
        'transaction_type',
        'transaction_date',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
