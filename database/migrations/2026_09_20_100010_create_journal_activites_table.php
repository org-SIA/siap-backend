<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_activites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 80);
            $t->string('sujet_type', 80)->nullable();
            $t->string('sujet_id', 40)->nullable();
            $t->string('niveau', 10)->default('info'); // info | securite
            $t->string('request_id', 20)->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 250)->nullable();
            $t->json('proprietes')->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index('action');
            $t->index(['sujet_type', 'sujet_id']);
            $t->index('user_id');
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_activites');
    }
};
