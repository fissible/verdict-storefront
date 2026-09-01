<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WORKAROUND — delete when verdict#466 ships its add-column stub.
 *
 * verdict v0.15.0 added review_request_fingerprint to the evidence CREATE stub
 * only, so an upgrading install has no published migration for it while
 * verdict:validate errors on its absence. Definition mirrors the create stub
 * (char 64, nullable). https://github.com/fissible/verdict/issues/466
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table((string) config('verdict.evidence.table', 'verdict_evidence'), function (Blueprint $table): void {
            $table->char('review_request_fingerprint', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table((string) config('verdict.evidence.table', 'verdict_evidence'), function (Blueprint $table): void {
            $table->dropColumn('review_request_fingerprint');
        });
    }
};
