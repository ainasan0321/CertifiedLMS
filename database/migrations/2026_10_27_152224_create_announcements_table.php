<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->text('body');
            $table->string('target_type');
            $table->ulid('certification_id')->nullable()->constrained('certifications');
            $table->ulid('user_id')->nullable()->constrained('users');
            $table->integer('dispatched_count')->default(0);
            $table->timestamp('dispatched_at')->nullable();
            $table->ulid('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
