<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $import_id
 * @property int|null $category_id
 * @property CarbonInterface $posted_at
 * @property string $amount
 * @property string $currency
 * @property string $description
 * @property string|null $counterparty
 * @property string|null $balance
 * @property string $hash
 */
class Transaction extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'import_id',
        'category_id',
        'posted_at',
        'amount',
        'currency',
        'description',
        'counterparty',
        'balance',
        'hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posted_at' => 'date',
            'amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'description' => 'encrypted',
            'counterparty' => 'encrypted',
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
     * @return BelongsTo<Import, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<AiCategorization, $this>
     */
    public function aiCategorizations(): HasMany
    {
        return $this->hasMany(AiCategorization::class);
    }

    /**
     * Build the deduplication hash for a parsed transaction.
     * Re-uploading the same row must yield the same hash so the unique
     * (user_id, hash) index drops the duplicate.
     */
    public static function buildHash(
        int $userId,
        string $postedAt,
        string $amount,
        string $description,
        ?string $balance,
    ): string {
        return hash('sha256', implode('|', [
            (string) $userId,
            $postedAt,
            $amount,
            $description,
            $balance ?? '',
        ]));
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function isExpense(): Attribute
    {
        return Attribute::get(fn (): bool => (float) $this->amount < 0);
    }
}
