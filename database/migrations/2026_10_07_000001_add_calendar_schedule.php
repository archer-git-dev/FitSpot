<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_days', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->timestamps();
            $t->unique(['workspace_id', 'date']);
        });
        Schema::create('calendar_intervals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('calendar_day_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('start');
            $t->unsignedSmallInteger('end');
        });
        Schema::table('booking_rules', function (Blueprint $t) {
            $t->unsignedSmallInteger('buffer_after')->default(5)->change();
            $t->unsignedSmallInteger('slot_step')->default(5)->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_intervals');
        Schema::dropIfExists('calendar_days');
        Schema::table('booking_rules', function (Blueprint $t) {
            $t->unsignedSmallInteger('buffer_after')->default(10)->change();
            $t->unsignedSmallInteger('slot_step')->default(15)->change();
        });
    }
};
