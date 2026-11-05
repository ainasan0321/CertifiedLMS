<?php

declare(strict_types=1);

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
        Schema::create('meeting_reminders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('meeting_id');
            $table->string('window');
            $table->timestamps();
            $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');
            $table->unique(['meeting_id', 'window']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_reminders');
    }
};
