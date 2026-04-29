<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Bank;
use App\Enums\ImportStatus;
use Database\Factories\ImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property Bank $bank
 * @property string $original_filename
 * @property ImportStatus $status
 * @property string|null $failed_reason
 * @property int $transactions_count
 */
class Import extends Model
{
    /** @use HasFactory<ImportFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'bank',
        'original_filename',
        'status',
        'failed_reason',
        'transactions_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bank' => Bank::class,
            'status' => ImportStatus::class,
            'transactions_count' => 'integer',
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
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
