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
        if (!Schema::hasTable('salesforce_tokens')) {
            Schema::create('salesforce_tokens', function (Blueprint $table) {
                $table->unsignedInteger('id')->primary();
                $table->text('access_token');
                $table->string('instance_url', 255);
                $table->dateTime('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salesforce_tokens');
    }
};
