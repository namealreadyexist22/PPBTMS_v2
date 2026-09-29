<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('img_slug')->nullable();
            $table->mediumText('avatar_data')->nullable(); // base64-encoded image bytes
            $table->string('avatar_mime')->nullable();  // e.g. image/png, image/jpeg

            // Name fields
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('minitial')->nullable();
            $table->string('fullname')->virtualAs('concat(fname, " ", minitial, " ", lname)');

            // Core Auth
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            // Identity & Roles
            $table->string('google_id')->nullable()->unique();
            $table->tinyInteger('categories')->default(0);
            $table->boolean('is_activated')->default(false);

            // Audit & Tracking
            $table->timestamps();
            $table->string('user_created')->nullable();
            $table->string('user_updated')->nullable();
            $table->string('last_login_ip')->nullable();
        });

        // Keep these as they are for Laravel's core functionality
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
