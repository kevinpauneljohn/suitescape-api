<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutMethodDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'payout_method_id',
        'type',
        'account_name',
        'account_number',
        'role',
        'bank_name',
        'bank_type',
        'swift_code',
        'bank_code',
        'email',
        'phone',
        'dob',
        'pob',
        'citizenship',
        'billing_country',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'dob' => 'date',
    ];

    public function payoutMethod()
    {
        return $this->belongsTo(PayoutMethod::class);
    }
}
