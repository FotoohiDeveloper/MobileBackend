<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('identity', 100); // Increased length for phone/email
            $table->enum('channel', ['sms', 'email']);
            $table->enum('type', ['login', 'register', 'recovery'])->default('login');
            $table->unsignedInteger('code'); // Changed to integer for 6-digit OTP
            $table->string('token')->unique();
            $table->ipAddress('user_ip')->nullable();
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->index('identity'); // Added index for performance
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otps');
    }
};
