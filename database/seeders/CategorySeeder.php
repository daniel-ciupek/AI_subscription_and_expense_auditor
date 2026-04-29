<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Subscriptions', 'slug' => 'subscriptions', 'color' => '#7C3AED', 'icon' => 'repeat'],
            ['name' => 'Food', 'slug' => 'food', 'color' => '#F59E0B', 'icon' => 'utensils'],
            ['name' => 'Transport', 'slug' => 'transport', 'color' => '#22D3EE', 'icon' => 'car'],
            ['name' => 'Entertainment', 'slug' => 'entertainment', 'color' => '#EC4899', 'icon' => 'gamepad-2'],
            ['name' => 'Bills', 'slug' => 'bills', 'color' => '#EF4444', 'icon' => 'receipt'],
            ['name' => 'Salary', 'slug' => 'salary', 'color' => '#10B981', 'icon' => 'wallet'],
            ['name' => 'Other', 'slug' => 'other', 'color' => '#A1A1AA', 'icon' => 'circle-help'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
