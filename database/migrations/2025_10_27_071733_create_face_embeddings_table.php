<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // <-- این خط رو حتما اضافه کن

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // راه‌حل: اجرای دستور SQL خام برای فعال کردن اکستنشن
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector;');

        Schema::create('face_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // این ستون درسته، چون پکیج pgvector-php اینو اضافه می‌کنه
            $table->vector('embedding_vector', 512);

            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('face_embeddings');

        // راه‌حل: اجرای دستور SQL خام برای غیرفعال کردن
        DB::statement('DROP EXTENSION IF EXISTS vector;');
    }
};
