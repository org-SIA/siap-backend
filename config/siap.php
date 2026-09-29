<?php

return [
    // URL de la page de scan du frontend ; le code est ajouté à la fin. C'est ce que contient le QR imprimé.
    'scan_url' => env('SIAP_SCAN_URL', 'http://localhost:5173/s'),

    // Requêtes de scan autorisées par minute et par IP (anti-énumération).
    'scan_rate_limit' => (int) env('SIAP_SCAN_RATE_LIMIT', 30),

    // Durée de validité des jetons de connexion, en heures (7 jours par défaut).
    'token_ttl_hours' => (int) env('SIAP_TOKEN_TTL_HOURS', 168),

    // Disque de stockage des photos : "local" est PRIVÉ. En production : un disque S3/R2 privé.
    'storage_disk' => env('SIAP_STORAGE_DISK', 'local'),

    // Durée de vie des URLs signées de photos, en minutes.
    'signed_url_minutes' => (int) env('SIAP_SIGNED_URL_MINUTES', 10),

    // Documentation Swagger publique (/docs/api) hors environnement local.
    'docs_public' => (bool) env('SIAP_DOCS_PUBLIC', false),

    // Données de démonstration (comptes + codes QR de test). Jamais en production.
    'seed_demo' => (bool) env('SIAP_SEED_DEMO', false),

    // --- Notifications (cahier §4 : alerter les proches / le propriétaire quand une personne ou un objet est retrouvé) ---
    // SMS : 'log' (par défaut, écrit dans les logs — rien à configurer) ou 'http' (webhook générique).
    'sms_driver' => env('SIAP_SMS_DRIVER', 'log'),
    'sms_webhook_url' => env('SIAP_SMS_WEBHOOK_URL'),
    'sms_webhook_token' => env('SIAP_SMS_WEBHOOK_TOKEN'),

    // Push : 'log' (par défaut) ou 'fcm' (Firebase Cloud Messaging, clé serveur historique).
    'push_driver' => env('SIAP_PUSH_DRIVER', 'log'),
    'fcm_server_key' => env('SIAP_FCM_SERVER_KEY'),

    // Canal de log utilisé par les pilotes "log" (utile pour vérifier les envois en développement).
    'notifications_log_channel' => env('SIAP_NOTIFICATIONS_LOG_CHANNEL', 'stack'),
];
