<?php

namespace Database\Seeders;

use App\Models\PolicyPage;
use Illuminate\Database\Seeder;

class PolicyPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'privacy' => 'Privacy',
            'terms' => 'Terms',
            'delivery' => 'Delivery',
            'returns' => 'Returns',
            'contact' => 'Contact',
        ] as $slug => $title) {
            PolicyPage::firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'body' => '',
                    'is_published' => false,
                ],
            );
        }
    }
}
