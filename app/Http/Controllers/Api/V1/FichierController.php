<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Beneficiaire;
use App\Models\Objet;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FichierController extends Controller
{
    /** Sert une photo depuis le stockage privé. Accès uniquement via une URL signée à durée limitée. */
    public function photo(string $type, string $id): StreamedResponse
    {
        $modele = match ($type) {
            'beneficiaire' => Beneficiaire::find($id),
            'objet' => Objet::find($id),
            default => null,
        };

        if (! $modele || ! $modele->photo_path) {
            throw ApiException::introuvable('Photo introuvable.');
        }

        $disque = Storage::disk(config('siap.storage_disk'));
        if (! $disque->exists($modele->photo_path)) {
            throw new ApiException('STORAGE-404', 'Fichier introuvable dans le stockage.', 404, 'ERROR-MAJOR');
        }

        return $disque->response($modele->photo_path, null, ['Cache-Control' => 'private, max-age=300']);
    }
}
