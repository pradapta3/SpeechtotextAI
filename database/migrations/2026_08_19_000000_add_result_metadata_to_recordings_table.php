<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            // Metadata hasil, supaya antarmuka bisa menerangkan kapan dan dengan
            // apa sebuah transkrip / notulensi dibuat.
            $table->timestamp('transcribed_at')->nullable()->after('error');
            $table->timestamp('minutes_generated_at')->nullable()->after('minutes');
            $table->string('minutes_model')->nullable()->after('minutes_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn(['transcribed_at', 'minutes_generated_at', 'minutes_model']);
        });
    }
};
