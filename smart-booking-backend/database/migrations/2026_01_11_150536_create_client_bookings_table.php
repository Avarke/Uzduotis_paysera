<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_bookings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_id')
                  ->constrained('services')
                  ->cascadeOnDelete();

            $table->date('date');
            $table->time('start_time');

            // Optional but useful for clarity
            $table->time('end_time')->nullable();

            $table->string('client_email');

            $table->timestamps();

            // Prevent double booking (single coach)
            $table->unique(['date', 'start_time']);
            $table->index(['service_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_bookings');
    }
};
