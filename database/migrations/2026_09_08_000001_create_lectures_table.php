<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A weekly class slot for a course: "CSC400, Monday 10:00-11:30, Room B204"
        Schema::create('lectures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('day_of_week'); // 0 = Monday ... 6 = Sunday
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room', 60)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lectures');
    }
};
