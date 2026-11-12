<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catálogo de softwares (mesma estrutura dos cadastros simples)
        Schema::create('software', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Licenças: cada contrato ou compra de um software
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_id')->constrained('software');
            $table->string('name', 150);
            $table->string('license_type', 20);
            $table->string('assignment_type', 20);
            $table->unsignedInteger('seats')->default(1);
            $table->text('license_key')->nullable();
            $table->string('admin_account_email')->nullable();

            $table->date('purchase_date')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->boolean('auto_renew')->default(false);
            $table->string('billing_cycle', 20)->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('supplier', 120)->nullable();
            $table->string('invoice_number', 60)->nullable();
            $table->unsignedSmallInteger('alert_days')->default(30);

            $table->text('notes')->nullable();
            $table->date('retired_at')->nullable();
            $table->string('retirement_reason', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('software');
    }
};