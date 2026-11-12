<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained();
            $table->foreignId('employee_id')->nullable()->constrained();
            $table->foreignId('asset_id')->nullable()->constrained();

            $table->date('assigned_at');
            $table->foreignId('assigned_by')->constrained('users');

            // Liberação (vazia enquanto a licença está em uso)
            $table->date('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users');

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['license_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_assignments');
    }
};