<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BdDistrict;
use App\Models\BdDivision;
use App\Models\BdUpazila;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BdGeoSeeder extends Seeder
{
    /**
     * Global geo seeder - tenant-agnostic, idempotent via bbs_code / name_en.
     * Loads full BD geo from JSON in database/seeders/data/ (8 divisions, 64 districts, ~495 upazilas).
     * Falls back to inline Dhaka sample if JSON missing.
     */
    public function run(): void
    {
        $divisionsPathHyphen = database_path('seeders/data/bd-divisions.json');
        $districtsPathHyphen = database_path('seeders/data/bd-districts.json');
        $upazilasPathHyphen = database_path('seeders/data/bd-upazilas.json');
        $divisionsPath = database_path('seeders/data/bd_divisions.json');
        $districtsPath = database_path('seeders/data/bd_districts.json');
        $upazilasPath = database_path('seeders/data/bd_upazilas.json');

        $useHyphen = is_file($divisionsPathHyphen) && is_file($districtsPathHyphen) && is_file($upazilasPathHyphen);
        $useUnderscore = is_file($divisionsPath) && is_file($districtsPath) && is_file($upazilasPath);

        if ($useHyphen || $useUnderscore) {
            // Full JSON seeding: truncate global tables to avoid legacy Dhaka-only bbs_code 10 collision
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            BdUpazila::query()->delete();
            BdDistrict::query()->delete();
            BdDivision::query()->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            if ($useHyphen) {
                $rawDivisions = json_decode((string) file_get_contents($divisionsPathHyphen), true);
                $divisionsData = $rawDivisions['divisions'] ?? $rawDivisions;
                $bbsMap = ['1' => '10', '2' => '20', '3' => '30', '4' => '40', '5' => '50', '6' => '55', '7' => '60', '8' => '45'];
                $divisionIdMap = [];
                foreach ($divisionsData as $div) {
                    $id = (string) ($div['id'] ?? '');
                    $bbs = $bbsMap[$id] ?? $id;
                    $created = BdDivision::query()->updateOrCreate(
                        ['bbs_code' => $bbs],
                        [
                            'name_en' => $div['name'] ?? $div['name_en'],
                            'name_bn' => $div['bn_name'] ?? $div['name_bn'],
                            'bn_name' => $div['bn_name'] ?? $div['name_bn'] ?? null,
                            'url' => $div['url'] ?? null,
                            'lat' => $div['lat'] ?? null,
                            'lon' => $div['long'] ?? $div['lon'] ?? null,
                        ]
                    );
                    $divisionIdMap[$id] = $created->id;
                }

                $rawDistricts = json_decode((string) file_get_contents($districtsPathHyphen), true);
                $districtsData = $rawDistricts['districts'] ?? $rawDistricts;
                $districtIdMap = [];
                foreach ($districtsData as $dist) {
                    $origDivisionId = (string) ($dist['division_id'] ?? '');
                    $divisionId = $divisionIdMap[$origDivisionId] ?? null;
                    if ($divisionId === null) {
                        continue;
                    }
                    $bbs = $dist['id'] ?? $dist['bbs_code'] ?? $dist['name'];
                    // Make bbs_code globally unique using division bbs
                    $divisionBbs = BdDivision::query()->find($divisionId)?->bbs_code ?? $origDivisionId;
                    $uniqueBbs = $divisionBbs.'-'.$bbs;
                    $created = BdDistrict::query()->updateOrCreate(
                        ['bbs_code' => $uniqueBbs],
                        [
                            'division_id' => $divisionId,
                            'name_en' => $dist['name'] ?? $dist['name_en'],
                            'name_bn' => $dist['bn_name'] ?? $dist['name_bn'],
                            'lat' => $dist['lat'] ?? null,
                            'lon' => $dist['long'] ?? $dist['lon'] ?? null,
                            'url' => $dist['url'] ?? null,
                        ]
                    );
                    $districtIdMap[(string) $dist['id']] = $created->id;
                }

                $rawUpazilas = json_decode((string) file_get_contents($upazilasPathHyphen), true);
                $upazilasData = $rawUpazilas['upazilas'] ?? $rawUpazilas;
                foreach ($upazilasData as $upa) {
                    $origDistrictId = (string) ($upa['district_id'] ?? '');
                    $districtId = $districtIdMap[$origDistrictId] ?? null;
                    if ($districtId === null) {
                        continue;
                    }
                    BdUpazila::query()->updateOrCreate(
                        ['name_en' => $upa['name'], 'district_id' => $districtId],
                        ['name_bn' => $upa['bn_name'] ?? $upa['name']]
                    );
                }

                return;
            }

            $divisions = json_decode((string) file_get_contents($divisionsPath), true);
            $districts = json_decode((string) file_get_contents($districtsPath), true);
            $upazilas = json_decode((string) file_get_contents($upazilasPath), true);

            if (is_array($divisions)) {
                foreach ($divisions as $div) {
                    BdDivision::query()->updateOrCreate(
                        ['bbs_code' => $div['bbs_code']],
                        [
                            'name_en' => $div['name_en'],
                            'name_bn' => $div['name_bn'],
                            'bn_name' => $div['name_bn'] ?? null,
                            'url' => $div['url'] ?? null,
                            'lat' => $div['lat'] ?? null,
                            'lon' => $div['lon'] ?? null,
                        ]
                    );
                }
            }

            if (is_array($districts)) {
                foreach ($districts as $dist) {
                    $division = BdDivision::query()->where('bbs_code', $dist['division_bbs'])->first();
                    if ($division === null) {
                        continue;
                    }
                    BdDistrict::query()->updateOrCreate(
                        ['bbs_code' => $dist['bbs_code']],
                        [
                            'division_id' => $division->id,
                            'name_en' => $dist['name_en'],
                            'name_bn' => $dist['name_bn'],
                            'lat' => $dist['lat'] ?? null,
                            'lon' => $dist['lon'] ?? null,
                            'url' => $dist['url'] ?? null,
                        ]
                    );
                }
            }

            if (is_array($upazilas)) {
                foreach ($upazilas as $upa) {
                    $districtBbs = $upa['district_bbs'] ?? null;
                    $divisionBbs = $upa['division_bbs'] ?? null;
                    // District bbs is stored as "division-district" composite
                    $lookupBbs = $divisionBbs && $districtBbs ? $divisionBbs.'-'.$districtBbs : $districtBbs;
                    $district = $lookupBbs ? BdDistrict::query()->where('bbs_code', $lookupBbs)->first() : null;
                    if ($district === null && $districtBbs !== null) {
                        $district = BdDistrict::query()->where('bbs_code', $districtBbs)->first();
                    }
                    if ($district === null) {
                        continue;
                    }
                    BdUpazila::query()->updateOrCreate(
                        ['name_en' => $upa['name_en'], 'district_id' => $district->id],
                        ['name_bn' => $upa['name_bn']]
                    );
                }
            }

            return;
        }

        // Fallback: minimal Dhaka sample
        $dhakaDivision = BdDivision::query()->updateOrCreate(
            ['bbs_code' => '10'],
            [
                'name_en' => 'Dhaka',
                'name_bn' => 'ঢাকা',
                'bn_name' => 'ঢাকা',
                'url' => 'https://en.wikipedia.org/wiki/Dhaka_Division',
                'lat' => '23.8103',
                'lon' => '90.4125',
            ]
        );

        $districts = [
            ['bbs_code' => '13', 'name_en' => 'Dhaka', 'name_bn' => 'ঢাকা', 'lat' => '23.8103', 'lon' => '90.4125', 'upazilas' => ['Dhamrai', 'Dohar', 'Keraniganj', 'Nawabganj', 'Savar']],
            ['bbs_code' => '15', 'name_en' => 'Gazipur', 'name_bn' => 'গাজীপুর', 'lat' => '24.0022', 'lon' => '90.4267', 'upazilas' => ['Gazipur Sadar', 'Kaliakair', 'Kapasia', 'Sreepur', 'Kaliganj']],
            ['bbs_code' => '19', 'name_en' => 'Narayanganj', 'name_bn' => 'নারায়ণগঞ্জ', 'lat' => '23.6339', 'lon' => '90.4965', 'upazilas' => ['Narayanganj Sadar', 'Bandar', 'Rupganj', 'Sonargaon', 'Araihazar']],
            ['bbs_code' => '21', 'name_en' => 'Tangail', 'name_bn' => 'টাঙ্গাইল', 'lat' => '24.2513', 'lon' => '89.9167', 'upazilas' => ['Tangail Sadar', 'Kalihati', 'Mirzapur', 'Gopalpur', 'Ghatail']],
            ['bbs_code' => '26', 'name_en' => 'Faridpur', 'name_bn' => 'ফরিদপুর', 'lat' => '23.6071', 'lon' => '89.8425', 'upazilas' => ['Faridpur Sadar', 'Alfadanga', 'Bhanga', 'Boalmari', 'Charbhadrasan']],
        ];

        foreach ($districts as $dist) {
            $upazilas = $dist['upazilas'];
            unset($dist['upazilas']);
            $district = BdDistrict::query()->updateOrCreate(
                ['bbs_code' => $dist['bbs_code']],
                array_merge($dist, ['division_id' => $dhakaDivision->id])
            );

            foreach ($upazilas as $upazilaName) {
                BdUpazila::query()->updateOrCreate(
                    ['name_en' => $upazilaName, 'district_id' => $district->id],
                    ['name_bn' => $upazilaName]
                );
            }
        }
    }
}
