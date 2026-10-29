<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Vacation planning questions ask which days of a period the participant wants off.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('max_value');
            $table->date('period_end')->nullable()->after('period_start');
            $table->boolean('weekdays_only')->default(false)->after('period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['period_start', 'period_end', 'weekdays_only']);
        });
    }
};
