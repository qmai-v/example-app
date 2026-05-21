<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_status_check CHECK (status IN ('active','suspended'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
