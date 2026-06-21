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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique(); // Unique pour éviter les doublons de fiches clients
            $table->string('email')->nullable();
            $table->string('gender', 10); // Pour stocker 'M' ou 'Mme'
            $table->text('notes')->nullable();  // Préférences du client (ex: "N'aime pas trop le sucre", "Allergique aux arachides")
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
