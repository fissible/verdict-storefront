<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The approval walkthrough's reviewer role: who may decide pending
            // approvals. Deliberately a flat flag — role machinery would teach
            // nothing about Verdict.
            $table->boolean('is_reviewer')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_reviewer');
        });
    }
};
