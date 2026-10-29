<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An event needs a start or a signup deadline, but not necessarily both.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Events without a start get their signup deadline as start, so the column can be required again.
     */
    public function down(): void
    {
        DB::table('events')->whereNull('starts_at')->update(['starts_at' => DB::raw('signup_deadline')]);

        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable(false)->change();
        });
    }
};
