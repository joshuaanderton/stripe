<?php

namespace Ja\Stripe\Actions\Tax;

use App\Models\Order;
use Illuminate\Support\Str;
use Stripe\StripeClient;
use Stripe\Tax\Calculation;

/**
 * @resource https://support.stripe.com/questions/understanding-stripe-tax-pricing
 */

class Automatic
{
    public static function run(Order $order): Calculation
    {
        $stripe = new StripeClient(env('STRIPE_SECRET_KEY'));
        $customerDetails = ['address_source' => 'billing'];
        $cart = $order->cart;

        if (! $address = $cart->billingAddress) {
            $address = $cart->shippingAddress;
            $customerDetails['address_source'] = 'shipping';
        }

        if ($address) {
            $customerDetails['address'] = $address->only([
                'line1',
                'line2',
                'state',
                'postal_code',
                'country',
            ]);
        } elseif (($ipAddress = request()->ip()) !== '127.0.0.1') {
            $customerDetails['ip_address'] = $ipAddress;
        }

        $products = $order->products;
        $products = $products->map(fn ($product) => [
            'amount' => $product->price,
            'quantity' => $product->pivot->quantity,
            'reference' => $product->name,
        ])->toArray();

        $shippingPrice = $order->selectedShippingRate->price ?? 0;

        return $stripe->tax->calculations->create([
            'currency' => Str::lower($cart->currency),
            'customer_details' => $customerDetails,
            'line_items' => $products,
            'shipping_cost' => ['amount' => $shippingPrice],
            'expand' => ['line_items'],
        ]);
    }
}
