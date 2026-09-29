<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaires', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();    // compte propriétaire du profil
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); // qui l'a enregistré (ex. agent)
            $t->string('type_profil', 30)->default('adulte'); // adulte | enfant | personne_vulnerable
            $t->string('statut', 20)->default('en_creation'); // en_creation | incomplet | actif | suspendu | archive
            $t->string('nom');
            $t->string('prenom');
            $t->date('date_naissance')->nullable();
            $t->string('telephone', 30)->nullable();
            $t->string('email')->nullable();
            $t->string('adresse')->nullable();
            $t->string('ville')->nullable();
            $t->string('photo_path')->nullable();
            $t->timestamps();

            $t->index(['nom', 'prenom']);
            $t->index('statut');
            $t->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaires');
    }
};
