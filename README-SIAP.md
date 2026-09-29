# Backend SIAP — API REST (Laravel 12 · PostgreSQL · Redis · Docker)

Socle complet issu du cahier des spécifications : authentification, rôles/permissions, bénéficiaires
(adulte / enfant / personne vulnérable), contacts, données médicales **chiffrées**, codes QR (cycle de vie complet),
objets perdus, scan à niveaux d'accès, **notifications SMS / e-mail / push** quand une personne ou un objet
est retrouvé, journal d'audit, format d'erreurs standard (§13.3), documentation Swagger.

## Démarrage — Docker (recommandé)

Seul prérequis : Docker + Docker Compose. La construction de l'image télécharge Laravel et ses
dépendances via Composer (accès Internet nécessaire **au moment du build**, pas ensuite).

```bash
docker compose up -d --build
```

C'est tout : Nginx, l'API (PHP-FPM), PostgreSQL, Redis et un worker de notifications démarrent ensemble ;
les migrations et les données de démonstration sont chargées automatiquement au premier lancement.

- API : `http://localhost:8000/api/v1`
- Documentation Swagger (à donner à ton collaborateur) : `http://localhost:8000/docs/api`
- Guide détaillé pour le frontend : `docs/API.md`
- Logs : `docker compose logs -f app` (ou `queue` pour les notifications)
- Tests : `docker compose exec app php artisan test`
- Tout arrêter : `docker compose down` (`-v` en plus pour effacer les données)

## Démarrage — sans conteneuriser l'API (alternative)

Si tu préfères exécuter PHP directement sur ta machine (PostgreSQL/Redis restant en Docker) :

```bash
./setup.sh                    # crée ./siap-api, PostgreSQL+Redis via Docker, migre, charge les données de démo
cd siap-api && php artisan serve
```
Sans Docker du tout, pour tester tout de suite : `SIAP_DB=sqlite ./setup.sh`.

## Partager l'API avant d'avoir un serveur

Un tunnel expose ton API locale avec une URL HTTPS publique en une commande :
`cloudflared tunnel --url http://localhost:8000` (ou `ngrok http 8000`).
Ajoute l'URL du frontend de ton collaborateur dans `CORS_ALLOWED_ORIGINS` (`.env.docker` ou `.env`), puis
`docker compose exec app php artisan config:clear` (ou `php artisan config:clear` en local).

## Ce qui est en place (⇄ cahier)

| Cahier | Implémentation |
|---|---|
| §6.5 niveaux d'accès | `ScanService` : `public` / `protege` / `medical`, filtrés côté serveur |
| §6.6 / §7.4.1 cycle de vie QR | `QrStatut::peutPasserA()` — transitions contrôlées, remplacement, désactivation |
| §6.7 / §9.3 données médicales | Table séparée, champs chiffrés (`APP_KEY`), lecture/écriture journalisées |
| §6.8 / §6.9 enfant / personne vulnérable retrouvé(e) | `POST /scan/{code}/signalement` → `AlerteService` (SMS + e-mail + push, file d'attente) |
| §6.10 objet retrouvé | `POST /scan/{code}/decouverte` → `AlerteService`, coordonnées masquées au public si confidentialité active |
| §4 notifications SMS / e-mail / push | `SmsGateway` / `PushGateway` (pilote `log` par défaut, `http`/`fcm` en un réglage `.env`) |
| §8.7 fichiers | Stockage privé + URLs signées de 10 min, 2 Mo max, jpeg/png/webp |
| §9 sécurité | Jetons Sanctum expirants, révocation à la désactivation d'un compte, limitation de débit, codes QR de 24 car. aléatoires |
| §5.12 historique | `journal_activites` en écriture seule (modification/suppression bloquées) |
| §13 erreurs | `ApiException` + `ApiResponse` : `{success, error{code,category,severity,message,timestamp,requestId,details}}` |

## Activer les notifications réelles

Par défaut, SMS et push sont **simulés** (écrits dans les logs) : rien à configurer pour développer et tester.
Pour de vrais envois, dans `.env.docker` (ou `.env`) :

- **SMS** : `SIAP_SMS_DRIVER=http`, `SIAP_SMS_WEBHOOK_URL=...` (passerelle Orange/MTN/Nexah/Twilio — adapter le corps de requête dans `App\Services\Notifications\HttpSmsGateway` au format exact du fournisseur choisi).
- **Push** : `SIAP_PUSH_DRIVER=fcm`, `SIAP_FCM_SERVER_KEY=...` (clé serveur Firebase).
- **E-mail** : remplacer `MAIL_MAILER=log` par un vrai transport (`smtp`, `ses`, `resend`…) et ses identifiants.

Puis `docker compose restart app queue`.

## Avant la mise en production (à ne pas oublier)

1. **Sauvegarder `APP_KEY`** : sans elle, les données médicales chiffrées sont irrécupérables.
2. `APP_ENV=production`, `APP_DEBUG=false`, `SIAP_SEED_DEMO=false` (les comptes de démo ne doivent jamais exister en production).
3. HTTPS obligatoire (Nginx derrière un reverse proxy TLS, ou Traefik/Caddy) ; `CORS_ALLOWED_ORIGINS` limité aux vraies origines ; `SIAP_SCAN_URL` = URL réelle de la page de scan.
4. Stockage des photos : disque privé S3/R2 (`SIAP_STORAGE_DISK`) plutôt que le volume local.
5. Sauvegardes PostgreSQL régulières (le volume `siap_pgdata` ne suffit pas comme sauvegarde) ; décider la durée de conservation du journal (§5.12).
6. Configurer de vrais fournisseurs SMS/push/e-mail (voir ci-dessus) ; `SIAP_DOCS_PUBLIC=false` si Swagger ne doit pas être public.

## Reste à faire (phase 2)

Mise en relation intermédiaire (messagerie anonymisée) pour les objets, export PDF des planches de QR à imprimer,
tableau de bord des statistiques (§12), tests de charge (§10.6).
