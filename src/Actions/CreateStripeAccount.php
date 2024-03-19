<?php

namespace Ja\Stripe\Actions;

use Stripe\StripeClient;

class CreateStripeAccount
{
    public static function run(string $email, string $country): \Stripe\Account
    {
        $stripe = new StripeClient(env('STRIPE_SECRET_KEY'));

        return $stripe->accounts->create([
            'type' => 'express',
            'country' => $country,
            'email' => $email,
            'capabilities' => [
                'transfers' => ['requested' => true],
                'card_payments' => ['requested' => true],
            ],
            'settings' => [
                'payouts' => [
                    'schedule' => [
                        'delay_days' => 7,
                        'interval' => 'weekly',
                        'weekly_anchor' => 'friday',
                    ],
                ],
            ],
        ]);
    }
}
