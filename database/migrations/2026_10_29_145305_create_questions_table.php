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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->boolean('is_required')->default(false);
            $table->json('options')->nullable();
            $table->integer('min_value')->nullable();
            $table->integer('max_value')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['event_id', 'position']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreign('capacity_question_id')->references('id')->on('questions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['capacity_question_id']);
        });

        Schema::dropIfExists('questions');
    }
};
