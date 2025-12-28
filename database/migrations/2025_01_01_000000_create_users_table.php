<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('second_last_name')->nullable();
            $table->string('phone');
            $table->string('email')->unique();
            $table->string('profile_photo_path')->nullable();
            $table->string('postal_code', 10)->index();
            $table->string('state');
            $table->string('municipality');
            $table->string('colony');
            $table->string('street');
            $table->string('external_number');
            $table->string('internal_number')->nullable();
            $table->string('phone_home')->nullable();
            $table->string('phone_office')->nullable();
            $table->string('role')->default('user');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
