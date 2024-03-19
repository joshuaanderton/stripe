<?php

namespace Ja\Stripe\Actions;

use Illuminate\Support\Str;
use Stripe\StripeClient;
use Stripe\Tax\Calculation;

/**
 * @resource https://support.stripe.com/questions/understanding-stripe-tax-pricing
 */
class CalculateStripeTax
{
    public static function run(array $products, string $currency, array $address, int $shippingCost): Calculation
    {
        $stripe = new StripeClient(env('STRIPE_SECRET_KEY'));
        $customerDetails = ['address_source' => 'billing'];

        if ($address['is_billing'] === false) {
            $customerDetails['address_source'] = 'shipping';
        }

        if ($address) {
            $customerDetails['address'] = collect($address)->only([
                'line1',
                'line2',
                'state',
                'postal_code',
                'country',
            ])->toArray();
        } elseif (($ipAddress = request()->ip()) !== '127.0.0.1') {
            $customerDetails['ip_address'] = $ipAddress;
        }

        return $stripe->tax->calculations->create([
            'currency' => Str::lower($currency),
            'customer_details' => $customerDetails,
            'line_items' => $products,
            'shipping_cost' => ['amount' => $shippingCost],
            'expand' => ['line_items'],
        ]);
    }
}
