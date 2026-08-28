<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BdDistrict;
use App\Models\BdDivision;
use App\Models\BdUpazila;
use Illuminate\Database\Seeder;

class BdGeoSeeder extends Seeder
{
    /**
     * Global geo seeder - tenant-agnostic, idempotent via bbs_code / name_en.
     * Seeds Dhaka division + its districts/upazilas for now; extend via JSON later.
     */
    public function run(): void
    {
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
