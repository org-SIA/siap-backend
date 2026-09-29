<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'telephone', 'structure_partenaire_id', 'actif', 'push_token',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
            'dernier_login_at' => 'datetime',
        ];
    }

    public function structurePartenaire(): BelongsTo
    {
        return $this->belongsTo(StructurePartenaire::class);
    }

    public function toApi(bool $avecPermissions = false): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'actif' => $this->actif,
            'structure_partenaire_id' => $this->structure_partenaire_id,
            'roles' => $this->getRoleNames()->values(),
        ];

        if ($avecPermissions) {
            $data['permissions'] = $this->getAllPermissions()->pluck('name')->values();
        }

        return $data;
    }
}
