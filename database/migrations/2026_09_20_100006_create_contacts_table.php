<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une seule table pour contacts, contacts d'urgence, parents/tuteurs et proches autorisés
        // (cahier §5.8 : éviter les duplications). Le champ "type" les distingue.
        Schema::create('contacts', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('beneficiaire_id')->constrained('beneficiaires')->cascadeOnDelete();
            $t->string('type', 20)->default('urgence'); // urgence | parent_tuteur | proche_autorise | autre
            $t->string('nom');
            $t->string('prenom')->nullable();
            $t->string('telephone', 30);
            $t->string('email')->nullable();
            $t->string('lien', 50)->nullable();          // mère, père, épouse, ami...
            $t->unsignedSmallInteger('priorite')->default(1);
            $t->boolean('visible_publiquement')->default(false); // affiché lors d'un scan public ?
            $t->string('preference_contact', 20)->default('appel'); // appel | sms | les_deux
            $t->timestamps();

            $t->index('beneficiaire_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
