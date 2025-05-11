<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->foreignId('currency_id')->constrained()->onDelete('cascade');
            $table->decimal('balance', 15, 2)->default(0.00); // موجودی برای هر ارز
            $table->decimal('committed_balance', 15, 2)->default(0.00); // مبلغ بلاک‌شده برای تعهد
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_balances');
    }
};