<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Enums\ProductStatus;
use App\Models\Campaign;
use App\Models\Product;
use Illuminate\Support\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for the products a campaign exposes on the
 * storefront. Offer pages and homepage campaign grids both resolve through
 * here so campaign eligibility (Campaign::scopeEligible), product
 * publication, and pivot sort order are enforced exactly once.
 */
class CampaignProductResolver
{
    public function __construct(private readonly ProductCardData $cards) {}

    /**
     * Eligible attached products for an eligible campaign, in pivot order.
     *
     * @return EloquentCollection<int, Product>
     */
    public function resolveForCampaign(Campaign $campaign, ?int $limit = null): EloquentCollection
    {
        if (! $campaign->isEligible()) {
            return new EloquentCollection;
        }

        return Product::query()
            ->published()
            ->with(['translations', 'variants', 'media', 'emiPlans'])
            ->join('campaign_product', 'campaign_product.product_id', '=', 'products.id')
            ->where('campaign_product.campaign_id', $campaign->getKey())
            ->orderBy('campaign_product.sort_order')
            ->select('products.*')
            ->when($limit !== null, fn ($query) => $query->limit($limit))
            ->get();
    }

    /**
     * "Up to X% OFF" figure: the largest card discount among the campaign's
     * eligible attached products. Null when nothing has a calculable
     * compare-at discount, so a misleading percentage is never shown.
     */
    public function maxDiscountForProducts(EloquentCollection $products): ?int
    {
        $cards = $this->cards->forMany($products);

        return $cards
            ->map(fn (array $card): ?int => $card['discount_percentage'])
            ->filter()
            ->max();
    }

    public function maxDiscountForCampaign(Campaign $campaign): ?int
    {
        return $this->maxDiscountForProducts($this->resolveForCampaign($campaign));
    }

    /**
     * Batched per-campaign max discounts for an index listing: two queries
     * total plus one batched card build, regardless of campaign count.
     *
     * @param  EloquentCollection<int, Campaign>  $campaigns
     * @return array<int, int> campaign_id => max discount percent
     */
    public function maxDiscountByCampaign(EloquentCollection $campaigns): array
    {
        if ($campaigns->isEmpty()) {
            return [];
        }

        $attachments = DB::table('campaign_product')
            ->join('products', 'products.id', '=', 'campaign_product.product_id')
            ->whereIn('campaign_product.campaign_id', $campaigns->pluck('id'))
            ->where('products.status', ProductStatus::Published->value)
            ->orderBy('campaign_product.sort_order')
            ->get(['campaign_product.campaign_id', 'campaign_product.product_id']);

        if ($attachments->isEmpty()) {
            return [];
        }

        $products = Product::query()
            ->published()
            ->with(['translations', 'variants', 'media', 'emiPlans'])
            ->whereIn('id', $attachments->pluck('product_id')->unique())
            ->get()
            ->keyBy('id');

        $discountById = $this->cards->forMany($products->values())
            ->mapWithKeys(fn (array $card): array => [$card['id'] => $card['discount_percentage']]);

        return $attachments->reduce(function (array $carry, object $row) use ($discountById): array {
            $discount = $discountById->get($row->product_id);

            if ($discount !== null && (! isset($carry[$row->campaign_id]) || $discount > $carry[$row->campaign_id])) {
                $carry[$row->campaign_id] = $discount;
            }

            return $carry;
        }, []);
    }
}
