<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable()->after('to_wallet_id')
                  ->constrained('merchants')->nullOnDelete();
            $table->foreignId('terminal_id')->nullable()->after('merchant_id')
                  ->constrained('terminals')->nullOnDelete();
            $table->string('capture_image_url', 1024)->nullable()->after('meta');
        });
    }
    public function down(): void {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merchant_id');
            $table->dropConstrainedForeignId('terminal_id');
            $table->dropColumn('capture_image_url');
        });
    }
};
