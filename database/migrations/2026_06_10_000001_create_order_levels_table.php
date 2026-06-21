<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('recipe_id')->nullable()->index();

            $table->integer('level_number')->comment('Numéro du niveau de l\'étage (1, 2, 3, ...)');
            $table->string('shape', 50)->nullable()->after('level_number')->comment('Forme du niveau (Rond, Carré, Rectangle, Cœur, Chiffre, Personnalisé)');
            $table->string('flavor_biscuit')->nullable()->comment('Saveur du biscuit');
            $table->string('flavor_cream')->nullable()->comment('Saveur de la crème');
            $table->string('filling')->nullable()->comment('Garniture');
            $table->decimal('diameter_cm', 6, 1)->nullable()->comment('Diamètre en cm (forme ronde)');
            $table->decimal('width_cm', 6, 1)->nullable()->after('diameter_cm')->comment('Largeur en cm (carré, rectangle)');
            $table->decimal('length_cm', 6, 1)->nullable()->after('width_cm')->comment('Longueur en cm (rectangle)');
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_levels');
    }
};
