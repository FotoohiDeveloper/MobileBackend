<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique(); // کد ارز (مثل USD، IRR)
            $table->string('name'); // نام ارز
            $table->string('symbol')->nullable(); // نماد ارز
            $table->bigInteger('price')->nullable(); // قیمت ارز
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
