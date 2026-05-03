<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DuplicateResolution;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $category_id
 * @property string $name
 * @property string $amount
 * @property string $currency
 * @property int $billing_cycle_days
 * @property CarbonInterface $last_charge_at
 * @property CarbonInterface|null $next_expected_charge_at
 * @property int|null $is_duplicate_of_id
 * @property DuplicateResolution|null $duplicate_resolution
 */
class Subscription extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'amount',
        'currency',
        'billing_cycle_days',
        'last_charge_at',
        'next_expected_charge_at',
        'is_duplicate_of_id',
        'duplicate_resolution',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'billing_cycle_days' => 'integer',
            'last_charge_at' => 'date',
            'next_expected_charge_at' => 'date',
            'duplicate_resolution' => DuplicateResolution::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'is_duplicate_of_id');
    }
}
