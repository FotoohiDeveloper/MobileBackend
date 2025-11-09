<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('name');
            $table->string('location_description')->nullable();
            $table->string('api_key_hash')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('terminals');
    }
};
