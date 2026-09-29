<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objets', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();    // propriétaire ayant un compte
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('categorie', 100);
            $t->text('description')->nullable();
            $t->string('photo_path')->nullable();
            $t->string('statut', 30)->default('enregistre');
            $t->boolean('confidentialite_active')->default(true); // masque les coordonnées du propriétaire
            // Propriétaire sans compte (enregistré par un agent)
            $t->string('proprietaire_nom')->nullable();
            $t->string('proprietaire_telephone', 30)->nullable();
            $t->string('proprietaire_email')->nullable();
            $t->timestamps();

            $t->index('statut');
            $t->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('objets');
    }
};
