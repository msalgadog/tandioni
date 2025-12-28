<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tandas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('amount', 12, 2);
            $table->enum('frequency', ['weekly', 'biweekly', 'monthly']);
            $table->unsignedInteger('participants_count');
            $table->date('start_date');
            $table->date('delivery_date');
            $table->enum('payment_mode', ['intermediary', 'direct']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tandas');
    }
};
