<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_INITIATED = 'initiated';

    public const STATUS_AWAITING_3D = 'awaiting_3d';

    public const STATUS_PROVISIONING = 'provisioning';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNKNOWN = 'unknown';

    protected $fillable = [
        'order_id',
        'merchant_order_id',
        'amount',
        'currency_code',
        'status',
        'gateway_order_id',
        'provision_number',
        'rrn',
        'stan',
        'response_code',
        'response_message',
        'reference_id',
        'business_key',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
