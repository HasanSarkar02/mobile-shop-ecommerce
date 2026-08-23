<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Services\Storefront\CampaignProductResolver;
use App\Services\Storefront\ProductCardData;
use App\Services\WishlistService;
use Illuminate\Database\Eloquent\Builder;

class OfferController extends Controller
{
    public function __construct(
        private readonly CampaignProductResolver $campaignProducts,
        private readonly ProductCardData $cards,
    ) {}

    public function index()
    {
        $sort = request()->query('sort') === 'ending_soon' ? 'ending_soon' : 'newest';

        $campaigns = Campaign::query()
            ->eligible()
            ->with(['banners' => fn ($query) => $query->where('is_active', true)])
            ->when($sort === 'newest', fn (Builder $query) => $query->orderByDesc('starts_at'))
            ->when($sort === 'ending_soon', fn (Builder $query) => $query->orderByRaw('ends_at IS NULL ASC, ends_at ASC'))
            ->get();

        return view('storefront.offers.index', [
            'campaigns' => $campaigns,
            'discounts' => $this->campaignProducts->maxDiscountByCampaign($campaigns),
            'sort' => $sort,
            'heroImage' => $campaigns->map(fn (Campaign $c) => $c->heroImageUrl())->first(fn (?string $url) => $url !== null),
        ]);
    }

    public function show(string $slug)
    {
        $offer = Campaign::query()
            ->eligible()
            ->where('slug', $slug)
            ->firstOrFail();

        $offer->load(['banners' => fn ($query) => $query->where('is_active', true)]);

        $products = $this->campaignProducts->resolveForCampaign($offer);
        $wishlistedIds = app(WishlistService::class)->wishlistedProductIds($products->pluck('id'));
        $cards = $this->cards->forMany($products, $wishlistedIds);
        $coupons = Coupon::query()
            ->currentlyActive()
            ->where('campaign_id', $offer->getKey())
            ->get();

        return view('storefront.offers.show', [
            'offer' => $offer,
            'cards' => $cards,
            'maxDiscount' => $this->campaignProducts->maxDiscountForCampaign($offer),
            'coupons' => $coupons,
        ]);
    }
}
