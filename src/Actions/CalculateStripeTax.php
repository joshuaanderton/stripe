<?php

namespace Ja\Stripe\Actions;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Stripe\StripeClient;
use Stripe\Tax\Calculation;

/**
 * @resource https://support.stripe.com/questions/understanding-stripe-tax-pricing
 */
class CalculateStripeTax
{
    public static function run(string $currency, array $lineItems, int $shippingCost, ?array $customerAddress = null, ?string $customerIp = null): Calculation
    {
        if ($customerAddress === null && $customerIp === null) {
            throw new InvalidArgumentException('Either customer address or IP address must be provided');
        }

        $stripe = new StripeClient(env('STRIPE_SECRET_KEY'));
        $customerDetails = collect();

        if ($customerAddress) {
            if (($customerAddress['is_billing'] ?? null) === false) {
                $customerDetails = $customerDetails->put('address_source', 'shipping');
            } else {
                $customerDetails = $customerDetails->put('address_source', 'billing');
            }

            $customerDetails->put('address', (
                collect($customerAddress)
                    ->only([
                        'line1',
                        'line2',
                        'state',
                        'postal_code',
                        'country',
                    ])
                    ->toArray()
            ));
        } else if ($customerIp) {
            $customerDetails->put('ip_address', $customerIp);
        }

        return $stripe->tax->calculations->create([
            'currency' => Str::lower($currency),
            'customer_details' => $customerDetails->toArray(),
            'line_items' => $lineItems,
            'shipping_cost' => ['amount' => $shippingCost],
            'expand' => ['line_items'],
        ]);
    }
}
