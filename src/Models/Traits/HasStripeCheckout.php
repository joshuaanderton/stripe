<?php

namespace Ja\Stripe\Models\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Stripe\StripeClient;

trait HasStripeCheckout
{
    private function stripeClient()
    {
        return new StripeClient(env('STRIPE_SECRET'));
    }

    public function stripePaymentIntentSucceeded(): bool
    {
        return ($this->stripePaymentIntent['status'] ?? null) === 'succeeded';
    }

    public function getStripePaymentIntentAttribute(): ?Collection
    {
        if (! $this->stripe_payment_intent_id) {
            return null;
        }

        $paymentIntent = Cache::get("order_{$this->id}_stripe_pi", fn () => (
            $this->stripePaymentIntent()?->toJSON()
        ));

        return collect(json_decode($paymentIntent, true))->only([
            'client_secret',
            'amount',
            'status',
            'shipping',
            'currency',
        ]);
    }

    public function stripePaymentIntent(): ?object
    {
        return $this->stripeClient()->paymentIntents->retrieve($this->stripe_payment_intent_id);
    }

    public function updateStripePaymentIntent(): self
    {
        $this
            ->stripeClient()
            ->paymentIntents
            ->update($this->stripe_payment_intent_id, [
                'amount' => $this->total ?: 100, // Total needs to be at least 100 cents
                'currency' => str($this->currency)->lower(),
            ]);

        return $this;
    }
}
