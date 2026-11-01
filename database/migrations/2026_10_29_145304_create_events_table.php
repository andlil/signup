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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('slug', 32)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('signup_deadline')->nullable();
            $table->boolean('allow_late_cancellation')->default(false);
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedBigInteger('capacity_question_id')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('notification_mode')->default('each_signup');
            $table->timestamp('summary_sent_at')->nullable();
            $table->timestamp('export_sent_at')->nullable();
            $table->unsignedSmallInteger('retention_days')->default(30);
            $table->timestamps();

            $table->index(['user_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
