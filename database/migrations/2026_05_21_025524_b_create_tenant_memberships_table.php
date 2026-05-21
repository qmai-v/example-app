<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('member');
            $table->timestamps();

            $table->unique(['user_id', 'tenant_id']);
            $table->index('tenant_id');
            $table->index('user_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tenant_memberships ADD CONSTRAINT tenant_memberships_role_check CHECK (role IN ('tenant_admin','member'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_memberships');
    }
};
