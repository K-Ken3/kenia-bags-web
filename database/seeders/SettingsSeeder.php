<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            'brand_name' => 'Isimbi Mart',
            'brand_logo' => '',
            'contact_email' => '',
            'contact_phone' => '',
            'whatsapp_number' => '',
            'delivery_fee_rwf' => '0',
            'delivery_fee_confirmed' => '0',
            'delivery_information' => '',
            'returns_information' => '',
            'momo_pay_code' => '',
            'manual_momo_enabled' => '0',
            'unpaid_order_expiry_minutes' => '60',
        ] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        Setting::where('key', 'brand_name')
            ->where('value', '[BRAND]')
            ->update(['value' => 'Isimbi Mart']);
    }
}
