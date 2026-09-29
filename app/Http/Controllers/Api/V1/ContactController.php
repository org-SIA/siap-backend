<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContactType;
use App\Http\Controllers\Controller;
use App\Models\Beneficiaire;
use App\Models\Contact;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    private const TEL = 'regex:/^\+?[0-9\s\-]{8,20}$/';

    public function index(Beneficiaire $beneficiaire): JsonResponse
    {
        Gate::authorize('view', $beneficiaire);

        return ApiResponse::ok($beneficiaire->contacts->map(fn (Contact $c) => $c->toApi())->values());
    }

    public function store(Request $request, Beneficiaire $beneficiaire): JsonResponse
    {
        Gate::authorize('update', $beneficiaire);

        $data = $request->validate([
            'type' => ['required', Rule::enum(ContactType::class)],
            'nom' => ['required', 'string', 'max:120'],
            'prenom' => ['nullable', 'string', 'max:120'],
            'telephone' => ['required', 'string', self::TEL],
            'email' => ['nullable', 'email', 'max:190'],
            'lien' => ['nullable', 'string', 'max:50'],
            'priorite' => ['nullable', 'integer', 'min:1', 'max:99'],
            'visible_publiquement' => ['nullable', 'boolean'],
            'preference_contact' => ['nullable', Rule::in(['appel', 'sms', 'les_deux'])],
        ]);

        $contact = $beneficiaire->contacts()->create($data);
        Audit::log('contact.cree', $contact, ['beneficiaire_id' => $beneficiaire->id]);

        return ApiResponse::ok($contact->fresh()->toApi(), null, 201);
    }

    public function update(Request $request, Contact $contact): JsonResponse
    {
        Gate::authorize('update', $contact->beneficiaire);

        $data = $request->validate([
            'type' => ['sometimes', Rule::enum(ContactType::class)],
            'nom' => ['sometimes', 'string', 'max:120'],
            'prenom' => ['nullable', 'string', 'max:120'],
            'telephone' => ['sometimes', 'string', self::TEL],
            'email' => ['nullable', 'email', 'max:190'],
            'lien' => ['nullable', 'string', 'max:50'],
            'priorite' => ['nullable', 'integer', 'min:1', 'max:99'],
            'visible_publiquement' => ['nullable', 'boolean'],
            'preference_contact' => ['nullable', Rule::in(['appel', 'sms', 'les_deux'])],
        ]);

        $contact->update($data);
        Audit::log('contact.modifie', $contact, ['champs' => array_keys($data)]);

        return ApiResponse::ok($contact->fresh()->toApi());
    }

    public function destroy(Contact $contact): JsonResponse
    {
        Gate::authorize('update', $contact->beneficiaire);

        $contact->delete();
        Audit::log('contact.supprime', $contact, ['beneficiaire_id' => $contact->beneficiaire_id]);

        return ApiResponse::ok(['message' => 'Contact supprimé.']);
    }
}
