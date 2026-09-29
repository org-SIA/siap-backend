<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Miroir de decouvertes_objets, mais pour un enfant ou une personne vulnérable retrouvé (cahier §6.8/§6.9).
        Schema::create('signalements_beneficiaires', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('beneficiaire_id')->constrained('beneficiaires')->cascadeOnDelete();
            $t->string('signale_par_nom')->nullable();
            $t->string('signale_par_telephone', 30)->nullable();
            $t->text('message')->nullable();
            $t->string('lieu')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->boolean('contacts_alertes')->default(false);
            $t->string('ip', 45)->nullable();
            $t->timestamps();

            $t->index('beneficiaire_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signalements_beneficiaires');
    }
};
