<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->date('posted_at');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('PLN');
            $table->text('description');
            $table->text('counterparty')->nullable();
            $table->decimal('balance', 14, 2)->nullable();
            $table->string('hash', 64);
            $table->timestamps();

            $table->unique(['user_id', 'hash']);
            $table->index(['user_id', 'posted_at']);
            $table->index(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
