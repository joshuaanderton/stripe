<?php

namespace Ja\Stripe\Actions;

use Stripe\StripeClient;

class CreateStripeAccountLink
{
    public static function run(string $stripeAccountId, string $returnUrl, string $refreshUrl): \Stripe\AccountLink
    {
        $stripe = new StripeClient(env('STRIPE_SECRET_KEY'));

        return $stripe->accountLinks->create([
            'account' => $stripeAccountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
            'collection_options' => [
                'fields' => 'eventually_due',
                'future_requirements' => 'include'
            ],
        ]);
    }
}
