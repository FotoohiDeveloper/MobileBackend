<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('currency_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['citizen', 'normal', 'foreign'])->index(); // نوع کیف پول
            $table->decimal('balance', 15, 2)->default(0.00); // موجودی کیف پول
            $table->decimal('committed_balance', 15, 2)->default(0.00); // مبلغ بلاک‌شده برای تعهد
            $table->boolean('has_commitment')->default(false); // آیا قرارداد تعهد دارد
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};