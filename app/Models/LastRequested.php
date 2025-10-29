<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LastRequested extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'last_requested';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'user_uuid';

    /**
     * Primary key is a non-incrementing string (UUID).
     */
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     * Set to false by default in case the table
     * does not include created_at/updated_at columns.
     */
    public $timestamps = false;

    /**
     * The attributes that aren't mass assignable.
     */
    protected $guarded = [];
}


