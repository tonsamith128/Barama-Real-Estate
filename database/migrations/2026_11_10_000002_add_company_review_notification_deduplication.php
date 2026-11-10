<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_notifications', function (Blueprint $table): void {
            // Nullable: existing notifications retain their original identity.
            $table->string('dedupe_key', 160)->nullable();
            $table->unique(['user_id', 'dedupe_key'], 'notifications_owner_dedupe_unique');
        });
    }

    public function down(): void
    {
        Schema::table('account_notifications', function (Blueprint $table): void {
            $table->dropUnique('notifications_owner_dedupe_unique');
            $table->dropColumn('dedupe_key');
        });
    }
};
