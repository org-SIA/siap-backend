<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('telephone', 30)->nullable();
            $t->foreignId('structure_partenaire_id')->nullable()->constrained('structures_partenaires')->nullOnDelete();
            $t->boolean('actif')->default(true);
            $t->timestamp('dernier_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('structure_partenaire_id');
            $t->dropColumn(['telephone', 'actif', 'dernier_login_at']);
        });
    }
};
