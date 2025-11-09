<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {

            $table->string('role')->default('customer')->after('remember_token');

            $table->string('pin_hash')->nullable()->after('role');
            $table->decimal('pin_threshold', 19, 4)->default(0.0)->after('pin_hash');

            $table->string('kyc_image_url', 1024)->nullable()->after('id_card_image');

            $table->foreignUuid('merchant_id')->nullable()->after('kyc_image_url')
                  ->constrained('merchants')->nullOnDelete();

            if (Schema::hasColumn('users', 'id_card_image')) {
                $table->dropColumn('id_card_image');
            }
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merchant_id');
            $table->dropColumn(['role', 'pin_hash', 'pin_threshold', 'kyc_image_url']);
            $table->longText('id_card_image')->nullable();
        });
    }
};
