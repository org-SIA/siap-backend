# API SIAP v1 — guide pour le développeur frontend

Base URL : `http://localhost:8000/api/v1` (ou l'URL de l'environnement de test)
Documentation interactive (Swagger) : `/docs/api` — contrat OpenAPI : `/docs/api.json`
(à importer dans Postman/Insomnia, ou pour générer un client TypeScript).

## 1. Ce qu'il faut savoir avant de coder

- **Authentification** : `POST /auth/login` renvoie un `token`. L'envoyer dans chaque requête protégée : `Authorization: Bearer <token>`. Les jetons expirent (7 jours par défaut) → une réponse `AUTH-401` signifie « reconnecte-toi ».
- **Toujours envoyer** `Accept: application/json` (et `Content-Type: application/json` pour les corps JSON).
- **Format des réponses** — un seul, partout :
  - Succès : `{ "success": true, "data": ..., "meta": { "pagination": {...} }? }`
  - Erreur : `{ "success": false, "error": { "code", "category", "severity", "message", "timestamp", "requestId", "details" } }`
  - Afficher `error.message` à l'utilisateur ; se brancher sur `error.code` pour la logique. Donner `requestId` (ou l'en-tête `X-Request-Id`) quand on signale un bug.
- **Identifiants** : ULID (chaînes de 26 caractères) pour bénéficiaires, objets, QR, contacts. Les `users` gardent un entier.
- **Dates** : ISO 8601 UTC. **Pagination** : `?page=1&per_page=25` → `meta.pagination`.
- **Photos** : `photo_url` est une **URL signée qui expire (10 min)**. Ne jamais la stocker : la redemander à l'API. Envoi d'une photo : `multipart/form-data`, champ `photo` (jpeg/png/webp, max 2 Mo).
- **Limitation de débit** : réponse `429` / `SECURITY-429` + en-tête `Retry-After`. Le scan est plafonné par IP.
- **CORS** : l'origine de ton frontend doit figurer dans `CORS_ALLOWED_ORIGINS` (`.env` du backend).

## 2. Le point délicat : `GET /scan/{code}`

Route **publique**. Le serveur décide de ce que tu vois selon **qui scanne** :

| Qui scanne | `niveau_acces` | Ce qui est renvoyé |
|---|---|---|
| Personne non connectée (aucun jeton) | `public` | Prénom, nom, photo, contacts marqués « visibles », consignes d'assistance (personne vulnérable) |
| Agent de terrain / admin (jeton) | `protege` | + date de naissance, téléphone, adresse, **tous** les contacts, profil enfant / vulnérable complet |
| Structure de santé ou propriétaire du profil (jeton) | `medical` | + `information_medicale` |

> **Règle d'or : ne suppose jamais qu'un champ est présent.** Affiche ce qui est reçu. `information_medicale` est `null` si rien n'est renseigné (ne pas inventer de valeurs).
> Si un jeton expiré est envoyé, l'API répond comme pour un public : vérifie `niveau_acces` plutôt que de croire que la connexion est valide.

**Public — adulte** (`GET /scan/DEMOADULTE00000000000000`) :
```json
{ "success": true, "data": {
  "niveau_acces": "public", "type_cible": "beneficiaire", "profil_incomplet": false,
  "beneficiaire": { "id": "01J...", "type_profil": "adulte", "nom": "Mbarga", "prenom": "Jean", "photo_url": null },
  "contacts": [ { "nom": "Mbarga", "prenom": "Marie", "lien": "Épouse", "telephone": "+237 699 11 22 33", "preference_contact": "appel" } ]
} }
```
**Structure de santé** : mêmes champs que `protege`, plus :
```json
"information_medicale": { "beneficiaire_id": "01J...", "groupe_sanguin": "O+", "allergies": "Pénicilline",
  "traitements": "...", "informations_essentielles": "...", "updated_at": "..." }
```
**Objet** (`type_cible: "objet"`) : `objet {id, categorie, description, statut, photo_url}`, `proprietaire` (**`null` au public** si la confidentialité est active), `peut_signaler_decouverte: true`.
→ Pour signaler : `POST /scan/{code}/decouverte` `{ nom?, telephone? | message?, lieu?, latitude?, longitude? }` (au moins `telephone` ou `message`).

**Erreurs de scan** : `QR-404` code inconnu/illisible · `QR-410` code désactivé, suspendu ou pas encore activé · `PROFILE-404` profil non disponible (archivé, suspendu, en création).

## 3. Rôles et permissions

| Rôle | Peut faire |
|---|---|
| `utilisateur` | Créer et gérer **ses** profils, contacts, objets ; données médicales de ses profils |
| `agent_terrain` | Créer/modifier tous les profils, gérer les QR, gérer tous les objets |
| `structure_sante` | Lire/modifier les données médicales (elle arrive par le scan) |
| `admin` | Tout sauf le médical ; utilisateurs, structures partenaires, journal |

`GET /auth/me` renvoie `roles` **et** `permissions` : s'en servir pour afficher/masquer les écrans (l'API refuse de toute façon avec `ACCESS-403`).

## 4. Endpoints

🔓 public · 🔑 jeton requis · 👥 = permission/rôle indiqué

| Méthode & chemin | Accès | Description |
|---|---|---|
| `GET /health` | 🔓 | État de l'API et de la base |
| `POST /auth/register` | 🔓 | Inscription (`name, email, password, telephone?`) → jeton |
| `POST /auth/login` | 🔓 | `email, password` → `token, expires_at, user` |
| `POST /auth/logout` · `GET /auth/me` | 🔑 | Déconnexion · utilisateur + rôles + permissions |
| `GET /scan/{code}` | 🔓 (jeton optionnel) | Scan d'un QR (voir §2) |
| `POST /scan/{code}/decouverte` | 🔓 | Signaler un objet trouvé |
| `GET /beneficiaires` | 🔑 | Liste (`q, type_profil, statut, page, per_page`). Personnel : tous ; sinon : les siens |
| `POST /beneficiaires` | 🔑 | Créer (`type_profil` = `adulte`\|`enfant`\|`personne_vulnerable`, `nom, prenom, ...`) |
| `GET/PUT/DELETE /beneficiaires/{id}` | 🔑 | Détail complet / modifier / archiver |
| `POST /beneficiaires/{id}/statut` | 🔑 | `{statut}` : `en_creation, incomplet, actif` (+ `suspendu, archive` pour le personnel) |
| `POST /beneficiaires/{id}/photo` | 🔑 | Envoi de la photo (multipart) |
| `GET/POST /beneficiaires/{id}/contacts` | 🔑 | Contacts (`type` = `urgence`\|`parent_tuteur`\|`proche_autorise`\|`autre`, `visible_publiquement`...) |
| `PUT/DELETE /contacts/{id}` | 🔑 | Modifier / supprimer un contact |
| `GET/PUT /beneficiaires/{id}/information-medicale` | 👥 `medical.*` ou propriétaire | Données médicales (journalisées) |
| `PUT /beneficiaires/{id}/profil-enfant` · `.../profil-vulnerable` | 🔑 | Infos d'assistance (selon le type du profil) |
| `GET/POST /objets` · `GET/PUT /objets/{id}` | 🔑 | Objets perdus |
| `POST /objets/{id}/statut` · `.../photo` | 🔑 | Changer l'état · photo |
| `GET /codes-qr` · `POST /codes-qr {quantite}` | 👥 `qr.gerer` | Lister · générer un lot de codes vierges |
| `GET /codes-qr/code/{code}` · `GET /codes-qr/{id}` | 👥 `qr.gerer` | Retrouver un code (ex. autocollant vierge scanné par l'agent) |
| `POST /codes-qr/{id}/associer` | 👥 `qr.gerer` | `{cible_type: beneficiaire\|objet, cible_id, activer?}` |
| `POST /codes-qr/{id}/statut` | 👥 `qr.gerer` | `{statut: actif\|suspendu\|desactive}` |
| `POST /codes-qr/{id}/remplacer` | 👥 `qr.gerer` | Nouveau code actif ; l'ancien devient `remplace` |
| `GET /codes-qr/{id}/image` | 👥 `qr.gerer` | Image SVG du QR (contenu = URL `SIAP_SCAN_URL/{code}`) |
| `GET/POST/PUT /admin/utilisateurs` | 👥 admin | Comptes |
| `GET/POST/PUT /admin/structures` | 👥 admin | Structures partenaires |
| `GET /admin/journal` | 👥 admin | Journal (`action, niveau, user_id, du, au`) |

**Flux type « agent de terrain »** : `POST /beneficiaires` → (contacts, photo) → `POST /beneficiaires/{id}/statut {actif}` → scanner l'autocollant → `GET /codes-qr/code/{code}` → `POST /codes-qr/{id}/associer {cible_type, cible_id, activer: true}`.

## 5. Codes d'erreur

| Code | HTTP | Signification |
|---|---|---|
| `AUTH-401` | 401 | Identifiants invalides, jeton absent ou expiré |
| `ACCESS-403` | 403 | Permission insuffisante / compte désactivé / URL signée invalide ou expirée |
| `VALIDATION-422` | 422 | Données invalides → `details.champs` = `{ champ: [messages] }` |
| `QR-404` · `QR-410` · `QR-409` | 404·410·409 | QR inconnu · désactivé/non activé · transition d'état interdite |
| `PROFILE-404` | 404 | Profil non disponible |
| `RESOURCE-404` | 404 | Ressource ou route introuvable |
| `OBJET-409` | 409 | Transition d'état d'objet interdite |
| `SECURITY-429` | 429 | Trop de requêtes (voir `Retry-After`) |
| `STORAGE-404` | 404 | Fichier absent du stockage |
| `USER-413` | 413 | Fichier trop volumineux |
| `ROUTE-405` · `NETWORK-408` | 405 · 408 | Méthode non autorisée · délai dépassé |
| `DATABASE-500` · `SERVER-500` | 500 | Erreur interne (message générique, détail dans les logs via `requestId`) |

`severity` ∈ `INFO`, `WARN`, `ERROR-MINOR`, `ERROR-MAJOR`, `CRITICAL`.

## 6. Données de démonstration (`SIAP_SEED_DEMO=true`)

Mot de passe de tous les comptes : `password`

| Compte | Rôle |
|---|---|
| `admin@siap.test` | admin |
| `agent@siap.test` | agent_terrain |
| `sante@siap.test` | structure_sante |
| `user@siap.test` | utilisateur (propriétaire des 3 profils et de l'objet de démo) |

| Code à scanner | Résultat |
|---|---|
| `DEMOADULTE00000000000000` | Adulte + contacts + données médicales |
| `DEMOENFANT00000000000000` | Enfant (contact parent visible) |
| `DEMOVULNERABLE0000000000` | Personne vulnérable (consignes d'assistance publiques) |
| `DEMOOBJET000000000000000` | Objet (coordonnées masquées au public) |
| `DEMODESACTIVE00000000000` | `QR-410` |
| `DEMOLIBRE000000000000000` | Vierge (à associer avec un compte agent) |
| n'importe quoi d'autre | `QR-404` |

Essai rapide :
```bash
curl -s http://localhost:8000/api/v1/scan/DEMOADULTE00000000000000 -H "Accept: application/json"
curl -s -X POST http://localhost:8000/api/v1/auth/login -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"sante@siap.test","password":"password"}'
```

## 7. Notifications (SMS / e-mail / push)

Quand un enfant ou une personne vulnérable est signalé retrouvé, ou qu'un objet est signalé découvert, l'API
alerte automatiquement les contacts autorisés — le frontend n'a **rien à faire de plus** que d'appeler
l'endpoint de signalement.

- `POST /scan/{code}/signalement` — enfant / personne vulnérable retrouvé(e). Mêmes champs que `/decouverte`
  (`nom?`, `telephone?` ou `message?`, `lieu?`, `latitude?`, `longitude?`). Alerte les contacts de type
  `parent_tuteur`, `proche_autorise` et `urgence` par SMS/e-mail selon leurs préférences, et par push si le
  bénéficiaire a un compte avec un jeton enregistré.
- `POST /scan/{code}/decouverte` — objet retrouvé. Alerte le propriétaire (compte lié ou coordonnées saisies).
- `POST /auth/push-token` 🔑 — l'app mobile enregistre son jeton FCM (`{ "push_token": "..." }`) pour recevoir les push.

En développement, les SMS et push envoyés sont visibles dans les logs du conteneur `app`
(`docker compose logs -f app | grep -i simulé`) : aucune configuration n'est nécessaire pour tester le flux complet.

## 8. Export et import de données (§14.4 / §14.5 du cahier)

Réservés à l'administration (permission `export.donnees` / `import.donnees`). Toute donnée médicale
exportée exige en plus `medical.voir`, et chaque export/import contenant des données sensibles est
journalisé avec le niveau `securite`.

### Export

| Endpoint | Filtres possibles |
|---|---|
| `GET /admin/export/beneficiaires` | `format` (csv\|json, défaut csv), `statut`, `type_profil`, `ville`, `du`, `au`, `inclure_medical` (1\|0, ignoré sans `medical.voir`) |
| `GET /admin/export/objets` | `format`, `statut`, `du`, `au` |
| `GET /admin/export/journal` | `format`, `niveau`, `du`, `au` (limité aux 5000 dernières lignes) |

Réponse : fichier téléchargeable (`Content-Disposition: attachment`), pas une enveloppe `{success,data}`.

### Import de bénéficiaires

`POST /admin/import/beneficiaires` — `multipart/form-data` : `fichier` (CSV, 5 Mo max), `apercu` (booléen, optionnel).

Colonnes du CSV (en-tête, ordre libre, insensible à la casse) :
`nom, prenom, type_profil, date_naissance, telephone, email, adresse, ville`
Seuls `nom`, `prenom` et `type_profil` (`adulte`\|`enfant`\|`personne_vulnerable`) sont obligatoires.

Avec `apercu=true` : rien n'est enregistré, la réponse indique combien de lignes seraient acceptées/rejetées
et pourquoi (`exemple_acceptes`, `exemple_rejetes`). Sans `apercu` : les lignes valides sont créées (statut
`incomplet`), les doublons (même nom + prénom + date de naissance, dans le fichier ou déjà en base) et les
lignes invalides sont rejetés sans bloquer le reste ; la réponse détaille `enregistres` / `rejetes`.
