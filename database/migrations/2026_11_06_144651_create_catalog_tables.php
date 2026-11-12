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
    foreach (['departments', 'categories', 'manufacturers'] as $tableName) {
        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}

public function down(): void
{
    foreach (['manufacturers', 'categories', 'departments'] as $tableName) {
        Schema::dropIfExists($tableName);
    }
}

};
