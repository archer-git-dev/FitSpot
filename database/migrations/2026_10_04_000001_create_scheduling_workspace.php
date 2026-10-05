<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('password')->nullable()->change();
            $t->string('pending_email')->nullable();
        });
        Schema::create('workspaces', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->unique()->constrained('users');
            $t->string('name', 100);
            $t->string('slug', 40)->unique();
            $t->string('timezone')->default('Europe/Moscow');
            $t->text('description')->nullable();
            $t->string('phone')->nullable();
            $t->string('location_name', 200)->nullable();
            $t->string('address', 500)->nullable();
            $t->string('photo_path')->nullable();
            $t->timestamps();
        });
        Schema::create('telegram_identities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users');
            $t->string('subject')->unique();
            $t->timestamps();
        });
        Schema::create('working_intervals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('day');
            $t->unsignedSmallInteger('start');
            $t->unsignedSmallInteger('end');
            $t->index(['workspace_id', 'day']);
        });
        Schema::create('booking_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('buffer_before')->default(0);
            $t->unsignedSmallInteger('buffer_after')->default(10);
            $t->unsignedInteger('lead_minutes')->default(120);
            $t->unsignedSmallInteger('horizon_days')->default(30);
            $t->unsignedSmallInteger('slot_step')->default(15);
            $t->unsignedInteger('cancel_minutes')->default(720);
            $t->unsignedInteger('reschedule_minutes')->default(720);
            $t->timestamps();
        });
        Schema::create('training_services', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('name', 100);
            $t->text('description')->nullable();
            $t->string('type');
            $t->string('format');
            $t->unsignedSmallInteger('duration');
            $t->unsignedInteger('price_kopecks');
            $t->unsignedSmallInteger('capacity');
            $t->string('location', 500)->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['training_services', 'booking_rules', 'working_intervals', 'telegram_identities', 'workspaces'] as $table) {
            Schema::dropIfExists($table);
        } Schema::table('users', fn (Blueprint $t) => $t->dropColumn('pending_email'));
    }
};
