<?php

namespace Database\Seeders;

use App\Models\District;
use Illuminate\Database\Seeder;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $districtsData = [
            ['name' => 'بيروت 1', 'electoral_district_id' => 1, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 18, 'seats' => 1],
                ['sect_id' => 7, 'seats' => 1],
                ['sect_id' => 2, 'seats' => 3],
                ['sect_id' => 3, 'seats' => 1],
                ['sect_id' => 19, 'seats' => 1]]],
            ['name' => 'بيروت 2', 'electoral_district_id' => 2, 'is_tracked' => true, 'sectarian_quotas' => [
                ['sect_id' => 1, 'seats' => 1],
                ['sect_id' => 10, 'seats' => 6],
                ['sect_id' => 11, 'seats' => 2],
                ['sect_id' => 6, 'seats' => 1],
                ['sect_id' => 5, 'seats' => 1]]],
            ['name' => 'صيدا', 'electoral_district_id' => 10, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 10, 'seats' => 2]]],
            ['name' => 'جزين', 'electoral_district_id' => 10, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 18, 'seats' => 2],
                ['sect_id' => 7, 'seats' => 1]]],
            ['name' => 'صور', 'electoral_district_id' => 11, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 11, 'seats' => 4]]],
            ['name' => 'قرى صيدا (الزهراني)', 'electoral_district_id' => 11, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 11, 'seats' => 2],
                ['sect_id' => 7, 'seats' => 1]]],
            ['name' => 'بنت جبيل', 'electoral_district_id' => 12, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 11, 'seats' => 3]]],
            ['name' => 'النبطية', 'electoral_district_id' => 12, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 11, 'seats' => 3]]],
            ['name' => 'حاصبيا', 'electoral_district_id' => 12, 'is_tracked' => true, 'sectarian_quotas' => [
                ['sect_id' => 1, 'seats' => 1],
                ['sect_id' => 10, 'seats' => 1],
                ['sect_id' => 11, 'seats' => 2],
                ['sect_id' => 6, 'seats' => 1]]],
            ['name' => 'مرجعيون', 'electoral_district_id' => 12, 'is_tracked' => false, 'sectarian_quotas' => []],
            ['name' => 'زحلة', 'electoral_district_id' => 13, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 10, 'seats' => 1],
                ['sect_id' => 11, 'seats' => 1],
                ['sect_id' => 18, 'seats' => 1],
                ['sect_id' => 6, 'seats' => 1],
                ['sect_id' => 2, 'seats' => 1],
                ['sect_id' => 7, 'seats' => 2]]],
            ['name' => 'راشيا', 'electoral_district_id' => 14, 'is_tracked' => true, 'sectarian_quotas' => [
                ['sect_id' => 1, 'seats' => 1],
                ['sect_id' => 10, 'seats' => 2],
                ['sect_id' => 11, 'seats' => 1],
                ['sect_id' => 6, 'seats' => 1]]],
            ['name' => 'البقاع الغربي', 'electoral_district_id' => 14, 'is_tracked' => false, 'sectarian_quotas' => []],
            ['name' => 'بعلبك', 'electoral_district_id' => 15, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 10, 'seats' => 2],
                ['sect_id' => 11, 'seats' => 6],
                ['sect_id' => 6, 'seats' => 1],
                ['sect_id' => 18, 'seats' => 1]]],
            ['name' => 'الهرمل', 'electoral_district_id' => 15, 'is_tracked' => false, 'sectarian_quotas' => []],
            ['name' => 'عكار', 'electoral_district_id' => 7, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 10, 'seats' => 3],
                ['sect_id' => 12, 'seats' => 1],
                ['sect_id' => 6, 'seats' => 2],
                ['sect_id' => 18, 'seats' => 1]]],
            ['name' => 'طرابلس', 'electoral_district_id' => 8, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 10, 'seats' => 5],
                ['sect_id' => 12, 'seats' => 1],
                ['sect_id' => 6, 'seats' => 1],
                ['sect_id' => 18, 'seats' => 1]]],
            ['name' => 'المنية', 'electoral_district_id' => 8, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 10, 'seats' => 1]]],
            ['name' => 'الضنية', 'electoral_district_id' => 8, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 10, 'seats' => 2]]],
            ['name' => 'زغرتا', 'electoral_district_id' => 9, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 18, 'seats' => 3]]],
            ['name' => 'بشري', 'electoral_district_id' => 9, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 18, 'seats' => 2]]],
            ['name' => 'الكورة', 'electoral_district_id' => 9, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 6, 'seats' => 3]]],
            ['name' => 'البترون', 'electoral_district_id' => 9, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 18, 'seats' => 2]]],
            ['name' => 'جبيل', 'electoral_district_id' => 3, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 11, 'seats' => 1],
                ['sect_id' => 18, 'seats' => 2]]],
            ['name' => 'كسروان', 'electoral_district_id' => 3, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 18, 'seats' => 5]]],
            ['name' => 'المتن', 'electoral_district_id' => 4, 'is_tracked' => false, 'sectarian_quotas' => [
                ['sect_id' => 7, 'seats' => 1],
                ['sect_id' => 6, 'seats' => 2],
                ['sect_id' => 2, 'seats' => 1],
                ['sect_id' => 18, 'seats' => 4]]],
            ['name' => 'بعبدا', 'electoral_district_id' => 5, 'is_tracked' => true, 'sectarian_quotas' => [
                ['sect_id' => 1, 'seats' => 1],
                ['sect_id' => 11, 'seats' => 2],
                ['sect_id' => 18, 'seats' => 3]]],
            ['name' => 'الشوف', 'electoral_district_id' => 6, 'is_tracked' => true, 'sectarian_quotas' => [
                ['sect_id' => 1, 'seats' => 2],
                ['sect_id' => 10, 'seats' => 2],
                ['sect_id' => 7, 'seats' => 1],
                ['sect_id' => 18, 'seats' => 3]]],
            ['name' => 'عاليه', 'electoral_district_id' => 6, 'is_tracked' => true, 'sectarian_quotas' => [['sect_id' => 18, 'seats' => 1],
                ['sect_id' => 1, 'seats' => 2],
                ['sect_id' => 6, 'seats' => 1],
                ['sect_id' => 18, 'seats' => 2]]],
        ];

        // Ensure `sectarian_quotas` arrays are JSON-encoded for DB insertion
        $prepared = array_map(function ($d) {
            $d['sectarian_quotas'] = isset($d['sectarian_quotas']) ? json_encode($d['sectarian_quotas']) : null;

            return $d;
        }, $districtsData);

        District::upsert($prepared, ['name'], ['electoral_district_id', 'is_tracked']);
    }
}
