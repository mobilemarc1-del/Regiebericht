<?php
// Kopieren nach config.php und ausfüllen. config.php NICHT ins Git-Repo hochladen!
return [
    // OpenAI-API-Schlüssel (https://platform.openai.com/api-keys)
    'openai_api_key' => 'sk-...',

    // Frei gewähltes Passwort – dasselbe in der App unter ⚙️ Stammdaten → 🎤 Sprache eintragen
    'app_token' => 'bitte-ein-langes-zufaelliges-passwort',

    // Von wo die App aufgerufen werden darf (GitHub-Pages-Adresse, ohne Pfad und ohne / am Ende)
    'allowed_origins' => [
        'https://mobilemarc1-del.github.io',
    ],

    // Spracherkennung: 'gpt-4o-transcribe' (beste Qualität), 'gpt-4o-mini-transcribe' (günstiger) oder 'whisper-1'
    'stt_model'    => 'gpt-4o-transcribe',
    // Sprache der Aufnahme: leer = automatisch erkennen (empfohlen: Albanisch, gebrochenes Deutsch oder Mix).
    // 'sq' nur setzen, wenn er ausschließlich Albanisch spricht.
    'stt_language' => '',

    // Modell für Übersetzung + Felder
    'llm_model' => 'gpt-4o-mini',

    // Optional: Protokolldatei (Transkript + Ergebnis), z. B. __DIR__ . '/sprache.log' – leer = aus
    'log_file' => '',
];
