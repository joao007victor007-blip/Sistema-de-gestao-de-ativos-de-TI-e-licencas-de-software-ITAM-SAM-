<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Licenças corporativas não têm quantidade: a coluna passa a aceitar vazio
        Schema::table('licenses', function (Blueprint $table) {
            $table->unsignedInteger('seats')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->unsignedInteger('seats')->nullable(false)->default(1)->change();
        });
    }
};