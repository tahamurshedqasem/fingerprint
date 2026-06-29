<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'contract_number',
        'terms',
        'user_name',
        'user_phone',
        'discount_percentage',
        'signature_hash',
        'signature_data',
        'signed_at',
        'qr_code',
        'is_signed',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'is_signed' => 'boolean',
        'discount_percentage' => 'decimal:2'
    ];
}