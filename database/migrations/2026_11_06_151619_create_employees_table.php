<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index();
            $table->string('registration', 30)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('job_title', 120)->nullable();
            $table->foreignId('department_id')->nullable()->constrained();
            $table->foreignId('location_id')->nullable()->constrained();
            $table->boolean('is_active')->default(true);
            $table->date('terminated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};