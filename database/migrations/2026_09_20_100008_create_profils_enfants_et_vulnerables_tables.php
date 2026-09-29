<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profils_enfants', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('beneficiaire_id')->unique()->constrained('beneficiaires')->cascadeOnDelete();
            $t->string('ecole')->nullable();
            $t->text('signes_distinctifs')->nullable();          // aide à confirmer l'identité
            $t->text('informations_reunification')->nullable();  // consignes pour retrouver les parents
            $t->timestamps();
        });

        Schema::create('profils_vulnerables', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('beneficiaire_id')->unique()->constrained('beneficiaires')->cascadeOnDelete();
            $t->text('situation')->nullable();
            $t->text('consignes_assistance')->nullable();
            $t->text('precautions')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profils_vulnerables');
        Schema::dropIfExists('profils_enfants');
    }
};
