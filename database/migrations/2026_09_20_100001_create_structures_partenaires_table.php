<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structures_partenaires', function (Blueprint $t) {
            $t->id();
            $t->string('nom');
            $t->string('type', 30)->default('sante'); // sante | institution | ong | autre
            $t->string('telephone', 30)->nullable();
            $t->string('email')->nullable();
            $t->string('adresse')->nullable();
            $t->string('ville')->nullable();
            $t->boolean('actif')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structures_partenaires');
    }
};
