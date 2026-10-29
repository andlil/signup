<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cover images are no longer supported, so the column and the uploaded files are removed.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('cover_image_path');
        });

        Storage::disk('public')->deleteDirectory('covers');
    }

    /**
     * Reverse the migrations.
     *
     * The column comes back empty; the deleted images cannot be restored.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('cover_image_path')->nullable()->after('description');
        });
    }
};
