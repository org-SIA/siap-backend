<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Champs chiffrés au niveau applicatif (cast "encrypted") : colonnes text.
        Schema::create('informations_medicales', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('beneficiaire_id')->unique()->constrained('beneficiaires')->cascadeOnDelete();
            $t->text('groupe_sanguin')->nullable();
            $t->text('allergies')->nullable();
            $t->text('traitements')->nullable();
            $t->text('informations_essentielles')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('informations_medicales');
    }
};
