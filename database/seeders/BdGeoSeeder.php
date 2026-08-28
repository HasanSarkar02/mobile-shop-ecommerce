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
        $divisionsPath = database_path('seeders/data/bd_divisions.json');
        $districtsPath = database_path('seeders/data/bd_districts.json');
        $upazilasPath = database_path('seeders/data/bd_upazilas.json');

        if (is_file($divisionsPath) && is_file($districtsPath) && is_file($upazilasPath)) {
            // Full JSON seeding: truncate global tables to avoid legacy Dhaka-only bbs_code 10 collision
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            BdUpazila::query()->delete();
            BdDistrict::query()->delete();
            BdDivision::query()->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

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
