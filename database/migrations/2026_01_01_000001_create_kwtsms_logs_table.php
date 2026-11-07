<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kwtsms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 64)->nullable()->index();
            // 2026-11-07 12:08 +03: text from the start (bulk sends log comma-separated lists); the later migration that widened it
            // needs doctrine/dbal on Laravel 10, which fresh Laravel 10 installs lack. Task laravel-ci-matrix.
            $table->text('recipient');
            $table->string('sender_id', 20)->nullable();
            $table->text('message');
            $table->enum('status', ['sent', 'failed', 'skipped', 'test'])->default('sent')->index();
            $table->string('event_type', 60)->nullable()->index();
            $table->boolean('is_test')->default(false);
            $table->integer('numbers_sent')->default(0);
            $table->decimal('points_charged', 10, 4)->default(0);
            $table->decimal('balance_after', 10, 4)->nullable();
            $table->json('api_request')->nullable();
            $table->json('api_response')->nullable();
            $table->string('error_code', 10)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kwtsms_logs');
    }
};
