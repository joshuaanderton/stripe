<?php

namespace Ja\Stripe\Models;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ja\Stripe\Merchantable;

class Merchant extends Model
{
    use HasFactory, Merchantable;

    public static function booted(): void
    {
        static::deleting(fn ($merchant) => (
            $merchant->deleteStripeAccount()
        ));
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
