<?php

use DefStudio\Telegraph\Models\TelegraphBot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('telegraph_bots', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->string('name')->nullable();

            $table->timestamps();
        });

        TelegraphBot::create([
            'token' => '8281869072:AAFDvKMK9UJsl3PhmcYZCFrk0opvMJd7IT4',
            'name' => 'Test bot',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('telegraph_bots');
    }
};
