<?php
/**
 * Regiebericht – Spracheingabe (Albanisch -> Deutsch)
 *
 * Nimmt eine Audioaufnahme entgegen, transkribiert sie (OpenAI Speech-to-Text),
 * übersetzt sie ins Deutsche und liefert die Formularfelder als JSON zurück.
 *
 * Aufruf: POST multipart/form-data
 *   audio    = Audiodatei (webm, mp4/m4a, wav, ...)
 *   kontext  = JSON (optional): { "datum": "YYYY-MM-DD", "mitarbeiter": [...], "auftraggeber": [...] }
 * Header:  X-App-Token: <APP_TOKEN aus config.php>
 */

declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'config.php fehlt – bitte config.sample.php kopieren und ausfüllen.']);
    exit;
}
$config = require $configFile;

// ------------------------------------------------------------------
// CORS
// ------------------------------------------------------------------
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $config['allowed_origins'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-App-Token');
    header('Access-Control-Max-Age: 86400');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function fail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail(405, 'Nur POST erlaubt.');
}

// ------------------------------------------------------------------
// Zugriffsschutz
// ------------------------------------------------------------------
$token = $_SERVER['HTTP_X_APP_TOKEN'] ?? '';
if (!hash_equals((string)$config['app_token'], (string)$token)) {
    fail(401, 'Ungültiger App-Token.');
}

// ------------------------------------------------------------------
// Eingaben prüfen
// ------------------------------------------------------------------
if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    fail(400, 'Keine Audiodatei empfangen (Upload-Fehler ' . ($_FILES['audio']['error'] ?? '?') . ').');
}
$audio = $_FILES['audio'];
if ($audio['size'] > 20 * 1024 * 1024) {
    fail(413, 'Aufnahme zu groß (max. 20 MB).');
}

$kontext = json_decode($_POST['kontext'] ?? '{}', true);
if (!is_array($kontext)) {
    $kontext = [];
}
$datumHeute   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($kontext['datum'] ?? '')) ? $kontext['datum'] : date('Y-m-d');
$mitarbeiter  = array_slice(array_map('strval', (array)($kontext['mitarbeiter'] ?? [])), 0, 50);
$auftraggeber = array_slice(array_map('strval', (array)($kontext['auftraggeber'] ?? [])), 0, 50);

// Dateiname mit passender Endung – die OpenAI-API erkennt das Format daran
$ext = strtolower(pathinfo($audio['name'] ?? '', PATHINFO_EXTENSION));
if (!in_array($ext, ['webm', 'mp4', 'm4a', 'mp3', 'wav', 'ogg', 'mpeg', 'mpga'], true)) {
    $ext = 'webm';
}

// ------------------------------------------------------------------
// HTTP-Helfer
// ------------------------------------------------------------------
function openai(string $endpoint, array $config, $body, bool $multipart): array
{
    $ch = curl_init('https://api.openai.com/v1/' . $endpoint);
    $headers = ['Authorization: Bearer ' . $config['openai_api_key']];
    if (!$multipart) {
        $headers[] = 'Content-Type: application/json';
        $body = json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 90,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        fail(502, 'Verbindung zu OpenAI fehlgeschlagen: ' . $err);
    }
    $data = json_decode($raw, true);
    if ($code >= 400 || !is_array($data)) {
        $msg = $data['error']['message'] ?? substr((string)$raw, 0, 300);
        fail(502, 'OpenAI-Fehler (' . $code . '): ' . $msg);
    }
    return $data;
}

// ------------------------------------------------------------------
// 1) Spracherkennung
// ------------------------------------------------------------------
// Namen als Hinweis mitgeben – verbessert die Erkennung von Kunden-/Mitarbeiternamen
$hinweis = trim(implode(', ', array_merge($mitarbeiter, $auftraggeber)));

$sttFields = [
    'model' => $config['stt_model'],
    'file'  => new CURLFile($audio['tmp_name'], $audio['type'] ?: 'application/octet-stream', 'aufnahme.' . $ext),
];
if (!empty($config['stt_language'])) {
    $sttFields['language'] = $config['stt_language'];
}
if ($hinweis !== '') {
    $sttFields['prompt'] = 'Regiebericht, Arbeitsstunden auf der Baustelle. Namen: ' . mb_substr($hinweis, 0, 800);
}

$stt = openai('audio/transcriptions', $config, $sttFields, true);
$transkript = trim((string)($stt['text'] ?? ''));
if ($transkript === '') {
    fail(422, 'Es wurde kein Text erkannt. Bitte deutlicher und etwas länger sprechen.');
}

// ------------------------------------------------------------------
// 2) Übersetzen + Felder extrahieren
// ------------------------------------------------------------------
$wochentag = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'][(int)date('w', strtotime($datumHeute))];

$system = <<<TXT
Du hilfst einem Handwerker, einen deutschen Regiebericht (Stundenbericht) auszufüllen.
Er spricht Albanisch, gebrochenes Deutsch oder eine Mischung aus beidem. Das Transkript kann Erkennungsfehler,
falsche Grammatik und falsch geschriebene Wörter enthalten – erschließe die gemeinte Bedeutung
(z. B. "Fliese Bad gemacht" = "Fliesen im Bad verlegt", "sieben bis vier" = 07:00–16:00).

Aufgaben:
1. Gib das Gesagte sinngemäß in klarem, sachlichem Deutsch wieder (Baustellen-Fachsprache) – egal ob es
   auf Albanisch oder in gebrochenem Deutsch gesagt wurde.
2. Fülle daraus die Felder. Felder, die nicht genannt wurden, bleiben leer ("" bzw. leere Liste / null).
   Erfinde nichts.
3. Schreibe eine kurze Rückübersetzung der erkannten Felder auf Albanisch, damit der Handwerker prüfen kann,
   ob alles richtig verstanden wurde (z. B. "Data: 25.11. · Klienti: Müller · 07:00–16:00, pushim 30 min · ...").

Regeln:
- Heute ist {$wochentag}, {$datumHeute}. Relative Angaben ("sot" = heute, "dje" = gestern, "të hënën" = Montag)
  in ein Datum YYYY-MM-DD umrechnen. Ohne Datumsangabe: datum = "".
- Uhrzeiten als HH:MM (24 h). "nga 7 deri 4" auf dem Bau bedeutet 07:00–16:00.
- Pause in Minuten ("gjysmë ore" = 30, "një orë" = 60). Nicht genannt: null.
- Wenn Stunden ohne Uhrzeiten genannt werden (z. B. "8 orë"), setze von = "07:00" und rechne bis aus
  (bei Pause null mit 30 min Pause).
- Mitarbeiter-Namen möglichst einem Namen aus dieser Liste zuordnen: {MITARBEITER}
  Spricht er nur von sich ("kam punuar", "unë"), verwende als Name "ICH".
- Auftraggeber möglichst einem Namen aus dieser Liste zuordnen: {AUFTRAGGEBER}
- taetigkeiten: ausgeführte Arbeiten als kurze deutsche Sätze bzw. Stichpunkte, je Zeile ein Punkt mit "- ".
- material: einheit nur aus: Stk, m, m², m³, kg, l, Std, psch.
TXT;
$system = str_replace(
    ['{MITARBEITER}', '{AUFTRAGGEBER}'],
    [$mitarbeiter ? implode('; ', $mitarbeiter) : '(keine)', $auftraggeber ? implode('; ', $auftraggeber) : '(keine)'],
    $system
);

$schema = [
    'type' => 'object',
    'additionalProperties' => false,
    'required' => ['deutsch', 'rueckuebersetzung_sq', 'datum', 'auftraggeber', 'bauvorhaben', 'auftragsnummer',
                   'mitarbeiter', 'taetigkeiten', 'vorkommnisse', 'material'],
    'properties' => [
        'deutsch'              => ['type' => 'string', 'description' => 'Vollständige deutsche Übersetzung des Gesagten'],
        'rueckuebersetzung_sq' => ['type' => 'string', 'description' => 'Kurze albanische Zusammenfassung der Felder zur Kontrolle'],
        'datum'                => ['type' => 'string'],
        'auftraggeber'         => ['type' => 'string'],
        'bauvorhaben'          => ['type' => 'string'],
        'auftragsnummer'       => ['type' => 'string'],
        'mitarbeiter' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['name', 'von', 'bis', 'pause'],
                'properties' => [
                    'name'  => ['type' => 'string'],
                    'von'   => ['type' => 'string'],
                    'bis'   => ['type' => 'string'],
                    'pause' => ['type' => ['integer', 'null']],
                ],
            ],
        ],
        'taetigkeiten' => ['type' => 'string'],
        'vorkommnisse' => ['type' => 'string'],
        'material' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['bezeichnung', 'menge', 'einheit'],
                'properties' => [
                    'bezeichnung' => ['type' => 'string'],
                    'menge'       => ['type' => ['number', 'null']],
                    'einheit'     => ['type' => 'string', 'enum' => ['Stk', 'm', 'm²', 'm³', 'kg', 'l', 'Std', 'psch']],
                ],
            ],
        ],
    ],
];

$chat = openai('chat/completions', $config, [
    'model'       => $config['llm_model'],
    'temperature' => 0,
    'messages'    => [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user',   'content' => "Transkript:\n" . $transkript],
    ],
    'response_format' => [
        'type'        => 'json_schema',
        'json_schema' => ['name' => 'regiebericht', 'strict' => true, 'schema' => $schema],
    ],
], false);

$felder = json_decode((string)($chat['choices'][0]['message']['content'] ?? ''), true);
if (!is_array($felder)) {
    fail(502, 'Antwort konnte nicht ausgewertet werden.');
}

// Optionales Protokoll (zum Nachvollziehen/Verbessern)
if (!empty($config['log_file'])) {
    @file_put_contents(
        $config['log_file'],
        json_encode(['zeit' => date('c'), 'transkript' => $transkript, 'felder' => $felder], JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

echo json_encode([
    'transkript' => $transkript,
    'felder'     => $felder,
], JSON_UNESCAPED_UNICODE);
