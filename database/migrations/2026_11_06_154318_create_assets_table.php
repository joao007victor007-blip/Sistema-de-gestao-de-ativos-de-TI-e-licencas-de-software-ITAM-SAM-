<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();

            // Identificação
            $table->string('code', 20)->nullable()->unique();
            $table->string('asset_tag', 50)->nullable()->unique();
            $table->foreignId('category_id')->constrained();
            $table->foreignId('manufacturer_id')->nullable()->constrained();
            $table->string('model', 120)->nullable();
            $table->string('serial_number', 100)->nullable()->unique();
            $table->text('description')->nullable();

            // Localização e situação
            $table->foreignId('location_id')->nullable()->constrained();
            $table->string('status', 20)->default('disponivel')->index();

            // Aquisição e garantia
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->string('invoice_number', 60)->nullable();
            $table->string('supplier', 120)->nullable();
            $table->date('warranty_until')->nullable();

            // Observações e descarte
            $table->text('notes')->nullable();
            $table->date('disposed_at')->nullable();
            $table->string('disposal_reason', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};