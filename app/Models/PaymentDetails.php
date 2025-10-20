<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentDetails extends Model
{
    protected $fillable = [
        'bitcoin_address',
        'bitcoin_instructions',
        'ethereum_address',
        'ethereum_instructions',
        'usdt_address_TRC20',
        'usdt_trc20_instructions',
        'usdt_address_ERC20',
        'usdt_erc20_instructions'
    ];

    protected $casts = [
        'bitcoin_instructions' => 'array',
        'ethereum_instructions' => 'array',
        'usdt_trc20_instructions' => 'array',
        'usdt_erc20_instructions' => 'array',
    ];
}
