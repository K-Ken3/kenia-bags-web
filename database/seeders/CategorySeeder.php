<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['name' => 'Everyday Bags', 'slug' => 'everyday-bags', 'position' => 1],
            ['name' => 'Work & Travel', 'slug' => 'work-travel', 'position' => 2],
            ['name' => 'Small Bags', 'slug' => 'small-bags', 'position' => 3],
        ] as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category + ['is_active' => true]);
        }
    }
}
