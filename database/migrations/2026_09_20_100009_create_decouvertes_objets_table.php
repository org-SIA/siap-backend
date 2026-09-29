<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decouvertes_objets', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->foreignUlid('objet_id')->constrained('objets')->cascadeOnDelete();
            $t->string('signale_par_nom')->nullable();
            $t->string('signale_par_telephone', 30)->nullable();
            $t->text('message')->nullable();
            $t->string('lieu')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->boolean('traitee')->default(false);
            $t->string('ip', 45)->nullable();
            $t->timestamps();

            $t->index('objet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decouvertes_objets');
    }
};
