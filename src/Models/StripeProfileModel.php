<?php

namespace Ja\Stripe\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Stripe\StripeClient;

class StripeProfileModel extends Model
{
    use HasFactory;

    public $table = 'stripe_profiles';

    protected $fillable = [
        'team_id',
        'stripe_customer_id',
        'stripe_account_id',
        'delinquent',
        'country',
    ];

    protected $casts = [
        'team_id' => 'integer',
        'delinquent' => 'boolean',
    ];

    public static function booted(): void
    {
        static::creating(fn ($stripeProfile) => (
            $stripeProfile->createStripeCustomer()
        ));

        static::deleting(fn ($stripeProfile) => (
            $stripeProfile
                ->deleteStripeCustomer()
                ->deleteStripeAccount()
        ));
    }

    private function stripeClient()
    {
        return new StripeClient(env('STRIPE_SECRET_KEY'));
    }

    protected function currency(): Attribute
    {
        return new Attribute(
            get: fn (): string => (string) str($this->stripeAccount->default_currency ?: 'CAD')->upper()
        );
    }

    public function stripeCustomer(): ?\Stripe\Customer
    {
        if ($stripeCustomer = $this->stripeClient()->customers->retrieve($this->stripe_customer_id)) {
            Cache::put("stripe_customer_{$this->id}", $stripeCustomer->toJSON());
        }

        return $stripeCustomer;
    }

    public function getStripeCustomerAttribute(): ?object
    {
        $json = Cache::get("stripe_customer_{$this->id}", fn () => $this->stripeCustomer()?->toJSON());

        return json_decode($json);
    }

    public function stripeAccount(): ?\Stripe\Account
    {
        if ($stripeAccount = $this->stripeClient()->accounts->retrieve($this->stripe_account_id)) {
            Cache::put("stripe_account_{$this->id}", $stripeAccount->toJSON());
        }

        return $stripeAccount;
    }

    public function getStripeAccountAttribute(): ?object
    {
        $json = Cache::get("stripe_account_{$this->id}", fn () => $this->stripeAccount()?->toJSON());

        return json_decode($json);
    }

    protected function createStripeCustomer(): self
    {
        $stripeCustomer = $this->stripeClient()->customers->create([
            'email' => $this->team->owner->email,
            'metadata' => ['team_id' => $this->team->id],
        ]);

        $this->stripe_customer_id = $stripeCustomer->id;

        return $this;
    }

    protected function deleteStripeCustomer(): self
    {
        $this->stripeClient()->customers->delete($this->stripe_customer_id);

        return $this;
    }

    public function createStripeAccount(): self
    {
        $stripeCustomer = $this->stripeClient()->accounts->create([
            'type' => 'express',
            'country' => $this->country,
            'email' => $this->team->owner->email,
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
            'settings' => [
                // 'branding' => [
                //     'icon' => $team->icon_url,
                //     'logo' => $team->logo_url,
                //     'primary_color' => $team->background_color,
                //     'secondary_color' => $team->font_color,
                // ],
                'payouts' => [
                    'schedule' => [
                        'delay' => 4,
                        'interval' => 'weekly'
                    ]
                ]
            ]
        ]);

        $this->stripe_account_id = $stripeCustomer->id;

        return $this;
    }

    public function newAccountLinkUrl(): string
    {
        // $stripeAccount = $this->stripeAccount();
        $stripeAccountLink = $this->stripeClient()->accountLinks->create([
            'account' => $this->stripe_account_id,
            'refresh_url' => route('shop.finances'),
            'return_url' => route('shop.finances'),
            'type' => 'account_onboarding',
            'collection_options' => [
                'fields' => 'eventually_due',
                'future_requirements' => 'include'
            ],
        ]);

        return $stripeAccountLink->url;
    }

    protected function deleteStripeAccount(): self
    {
        $this->stripeClient()->accounts->delete($this->stripe_account_id);

        return $this;
    }
}
