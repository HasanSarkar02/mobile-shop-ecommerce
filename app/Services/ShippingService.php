<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Address;
use App\Models\BdUpazila;
use App\Models\ShippingMethod;
use App\Models\TenantShippingRate;

class ShippingService
{
    /**
     * Quote shipping charge for current tenant based on geo hierarchy.
     * Priority: 1) exact upazila, 2) exact district, 3) fallback (district IS NULL).
     * Returns charge in minor units (BDT cents).
     */
    public function quote(?int $upazilaId = null, ?int $districtId = null, ?int $divisionId = null, ?Address $address = null): int
    {
        // Resolve from Address if provided (overrides explicit ids)
        if ($address !== null) {
            $upazilaId = $address->bd_upazila_id ?? $upazilaId;
            $districtId = $address->bd_district_id ?? $districtId;
            $divisionId = $address->bd_division_id ?? $divisionId;
        }

        // 1) Exact upazila match
        if ($upazilaId !== null) {
            $rate = TenantShippingRate::query()
                ->where('is_active', true)
                ->where('bd_upazila_id', $upazilaId)
                ->orderBy('sort_order')
                ->first();
            if ($rate !== null) {
                return (int) $rate->charge;
            }

            // Fallback via upazila's district if upazila not directly rated but its district is
            $upazila = BdUpazila::query()->find($upazilaId);
            if ($upazila !== null) {
                $districtId = $upazila->district_id;
            }
        }

        // 2) Exact district match (district-level rate has upazila_id null)
        if ($districtId !== null) {
            $rate = TenantShippingRate::query()
                ->where('is_active', true)
                ->where('bd_district_id', $districtId)
                ->whereNull('bd_upazila_id')
                ->orderBy('sort_order')
                ->first();
            if ($rate !== null) {
                return (int) $rate->charge;
            }

            // Also allow generic district match where upazila_id is not strictly null check (any district rate)
            $rate = TenantShippingRate::query()
                ->where('is_active', true)
                ->where('bd_district_id', $districtId)
                ->orderBy('sort_order')
                ->first();
            if ($rate !== null) {
                return (int) $rate->charge;
            }
        }

        // 2b) Division fallback if provided
        if ($divisionId !== null) {
            $rate = TenantShippingRate::query()
                ->where('is_active', true)
                ->where('bd_division_id', $divisionId)
                ->whereNull('bd_district_id')
                ->whereNull('bd_upazila_id')
                ->orderBy('sort_order')
                ->first();
            if ($rate !== null) {
                return (int) $rate->charge;
            }
        }

        // 3) Fallback outside rate (district IS NULL)
        $fallback = TenantShippingRate::query()
            ->where('is_active', true)
            ->whereNull('bd_district_id')
            ->whereNull('bd_upazila_id')
            ->orderBy('sort_order')
            ->first();

        if ($fallback !== null) {
            return (int) $fallback->charge;
        }

        // Ultimate fallback to flat ShippingMethod
        $method = ShippingMethod::query()->where('is_active', true)->orderBy('sort_order')->first();

        return $method !== null ? (int) $method->cost : 0;
    }

    /**
     * Quote for a guest address array or Address model.
     */
    public function quoteForGuest(array $guestAddress): int
    {
        $upazilaId = isset($guestAddress['bd_upazila_id']) ? (int) $guestAddress['bd_upazila_id'] : null;
        $districtId = isset($guestAddress['bd_district_id']) ? (int) $guestAddress['bd_district_id'] : null;
        $divisionId = isset($guestAddress['bd_division_id']) ? (int) $guestAddress['bd_division_id'] : null;

        // Support legacy keys
        if ($upazilaId === null && isset($guestAddress['bd_upazila_id'])) {
            $upazilaId = (int) $guestAddress['bd_upazila_id'];
        }

        return $this->quote($upazilaId, $districtId, $divisionId);
    }
}
