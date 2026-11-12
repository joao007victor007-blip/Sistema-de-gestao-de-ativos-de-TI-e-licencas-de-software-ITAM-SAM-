<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('location_id')->nullable()->constrained();

            // Entrega
            $table->date('checked_out_at');
            $table->foreignId('checked_out_by')->constrained('users');
            $table->string('checkout_condition', 255)->nullable();
            $table->boolean('term_signed')->default(false);
            $table->date('term_signed_at')->nullable();

            // Devolução (vazia enquanto o equipamento está com o colaborador)
            $table->date('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users');
            $table->string('checkin_condition', 255)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
    }
};