<?php

namespace Ja\Stripe\Models\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Stripe\StripeClient;

trait HasStripeCheckout
{
    private function stripeClient()
    {
        return new StripeClient(env('STRIPE_SECRET_KEY'));
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
        if (! $this->stripe_payment_intent_id) {
            return $this;
        }

        $shippingAddress = $this->shipping_address_id
            ? $this->shippingAddress()->first()
            : null;

        $data = array_merge([
                'amount' => $this->total,
                'currency' => str($this->currency)->lower(),
        ], $shippingAddress === null ? [] : [
            'shipping' => [
                'name' => $shippingAddress->name,
                'address' => $shippingAddress->only([
                    'line1',
                    'line2',
                    'city',
                    'country',
                    'postal_code',
                    'state',
                ]),
            ]
        ]);

        $this
            ->stripeClient()
            ->paymentIntents
            ->update($this->stripe_payment_intent_id, $data);

        return $this;
    }

    public function createStripePaymentIntent(): self
    {
        $cart = $this;

        if ($cart->stripe_payment_intent_id) {
            return $this;
        }

        $data = [
            'amount' => $cart->total ?: 100,
            'currency' => str($cart->currency)->lower(),
            'setup_future_usage' => 'off_session',
            'metadata' => ['internal_order_number' => $cart->number],
            'transfer_group' => $cart->number,
            'automatic_payment_methods' => ['enabled' => true],
        ];

        if ($stripeCustomerId = $cart->user?->stripe_customer_id) {
            $data['customer'] = $stripeCustomerId;
        }

        $paymentIntent = $this->stripeClient()->paymentIntents->create($data);

        $cart->update([
            'stripe_payment_intent_id' => $paymentIntent->id,
            'stripe_payment_intent_client_secret' => $paymentIntent->client_secret,
        ]);

        return $this;
    }
}
