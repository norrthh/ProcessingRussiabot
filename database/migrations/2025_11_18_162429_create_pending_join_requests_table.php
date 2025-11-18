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
        Schema::create('pending_join_requests', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->index();
            $table->string('chat_id');
            $table->bigInteger('message_id')->nullable();
            $table->timestamp('expires_at')->index();
            $table->boolean('processed')->default(false)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_join_requests');
    }
};
