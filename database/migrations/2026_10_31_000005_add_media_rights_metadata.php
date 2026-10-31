<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('usage_rights', 24)->default('unreviewed')->index();
            $table->string('rights_holder', 190)->nullable();
            $table->string('rights_reference', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex(['usage_rights']);
            $table->dropColumn(['usage_rights', 'rights_holder', 'rights_reference']);
        });
    }
};
