<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('work_rules', function (Blueprint $table) {
            $table->id();

            // 0 = Sunday ... 6 = Saturday
            $table->unsignedTinyInteger('day_of_week');

            $table->time('start_time');
            $table->time('end_time');

            // Slot size for availability generation
            $table->unsignedSmallInteger('slot_minutes')->default(30);

            $table->timestamps();

            $table->index(['day_of_week', 'start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_rules');
    }
};
