<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Bank;
use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    protected $model = Import::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank' => fake()->randomElement(Bank::cases()),
            'original_filename' => fake()->word().'.csv',
            'status' => ImportStatus::Done,
            'transactions_count' => fake()->numberBetween(0, 200),
        ];
    }
}
