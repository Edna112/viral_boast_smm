<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentDetails extends Model
{
    protected $fillable = [
        'bitcoin_address',
        'ethereum_address',
        'usdt_address_TRC20',
        'usdt_address_ERC20'
    ];
}
