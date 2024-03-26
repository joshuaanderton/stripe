<?php

namespace Ja\Stripe\Models;

use App\Models\Team;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Ja\Stripe\Actions\CreateStripeAccount;
use Ja\Stripe\Actions\CreateStripeAccountLink;
use Stripe\StripeClient;

class StripeProfile extends Model
{
    use HasFactory;

    public $table = 'stripe_profiles';

    protected $fillable = [
        'team_id',
        'stripe_account_id',
    ];

    protected $casts = ['team_id' => 'integer'];

    public static function booted(): void
    {
        static::deleting(fn ($stripeProfile) => (
            $stripeProfile->deleteStripeAccount()
        ));
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    private function stripeClient()
    {
        return new StripeClient(env('STRIPE_SECRET'));
    }

    protected function currency(): Attribute
    {
        return new Attribute(
            get: fn (): string => (string) str($this->stripeAccount->default_currency ?: 'CAD')->upper()
        );
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

    public function createStripeAccount(): self
    {
        tap(CreateStripeAccount::run(
            email: $this->team->owner->email,
            country: $this->team->country
        ), fn ($acct) => (
            $this->update(['stripe_account_id' => $acct->id])
        ));

        return $this;
    }

    public function newAccountLinkUrl(string $returnUrl, string $refreshUrl): string
    {
        $stripeAccountLink = CreateStripeAccountLink::run(
            stripeAccountId: $this->stripe_account_id,
            returnUrl: $returnUrl,
            refreshUrl: $refreshUrl
        );

        return $stripeAccountLink->url;
    }

    protected function deleteStripeAccount(): self
    {
        $this->stripeClient()->accounts->delete($this->stripe_account_id);

        return $this;
    }
}
