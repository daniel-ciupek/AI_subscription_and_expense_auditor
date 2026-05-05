<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('PLN');
            $table->unsignedSmallInteger('billing_cycle_days');
            $table->date('last_charge_at');
            $table->date('next_expected_charge_at')->nullable();
            $table->foreignId('is_duplicate_of_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'next_expected_charge_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
