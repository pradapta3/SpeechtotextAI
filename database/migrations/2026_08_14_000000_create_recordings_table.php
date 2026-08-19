<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordings', function (Blueprint $table) {
            $table->id();

            // Rekaman tidak butuh login: kepemilikan diikat ke kunci acak yang
            // hidup di session server.
            $table->string('owner_key', 64);

            $table->string('name');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->float('duration_seconds')->default(0);
            $table->string('language', 8)->default('id');
            $table->unsignedSmallInteger('chunk_seconds')->default(120);
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('total_chunks')->default(0);
            $table->json('segments')->nullable();
            $table->text('error')->nullable();

            $table->string('meeting_title')->nullable();
            $table->date('meeting_date')->nullable();
            $table->string('meeting_attendees')->nullable();
            $table->text('meeting_context')->nullable();
            $table->longText('minutes')->nullable();

            $table->timestamps();

            $table->index(['owner_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordings');
    }
};
