<?php

use Doctrine\DBAL\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 2026-11-07 12:08 +03: Laravel 10 can change a column only with doctrine/dbal. Fresh installs already create the column
        // as text, so without DBAL there is nothing to do; with it (or on Laravel 11+) the change runs as before.
        if (! $this->canChangeColumns()) {
            return;
        }

        Schema::table('kwtsms_logs', function (Blueprint $table) {
            // Expand recipient from varchar(30) to text to accommodate comma-separated
            // phone lists for bulk sends (e.g. "965XXXXXXXX,965XXXXXXXX,more").
            // 2026-11-05 11:24 +03: example uses the placeholder 965XXXXXXXX instead of sample
            // mobile numbers (they belong to real people), int-testnumber.
            $table->text('recipient')->change();
        });
    }

    public function down(): void
    {
        if (! $this->canChangeColumns()) {
            return;
        }

        Schema::table('kwtsms_logs', function (Blueprint $table) {
            $table->string('recipient', 30)->change();
        });
    }

    /**
     * Whether this Laravel version can change a column type (2026-11-07 12:08 +03: Laravel 11+ natively, 10 only with doctrine/dbal).
     */
    private function canChangeColumns(): bool
    {
        return version_compare(app()->version(), '11.0.0', '>=')
            || class_exists(Connection::class);
    }
};
