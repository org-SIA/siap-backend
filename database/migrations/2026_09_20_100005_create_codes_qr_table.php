<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codes_qr', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->string('code', 40)->unique();           // jeton public non devinable, encodé dans le QR
            $t->string('statut', 20)->default('cree');   // cree | associe | actif | suspendu | desactive | remplace
            $t->nullableUlidMorphs('cible');             // cible_type ('beneficiaire'|'objet') + cible_id
            // Auto-référence : la contrainte est ajoutée après coup (Schema::table plus bas), pas ici.
            // Sur PostgreSQL, une FK vers la clé primaire d'un ulid() défini dans le même Schema::create
            // peut se compiler AVANT la contrainte de clé primaire elle-même → "no unique constraint
            // matching given keys". La séparer en deux étapes évite complètement ce problème d'ordre.
            $t->ulid('remplace_par_id')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('active_le')->nullable();
            $t->timestamp('desactive_le')->nullable();
            $t->timestamps();

            $t->index('statut');
        });

        Schema::table('codes_qr', function (Blueprint $t) {
            $t->foreign('remplace_par_id')->references('id')->on('codes_qr')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes_qr');
    }
};
