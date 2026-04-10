<?php
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: text/plain; charset=utf-8');

$tenantId     = getenv('AZURE_TENANT_ID');
$clientId     = getenv('AZURE_CLIENT_ID');
$clientSecret = getenv('AZURE_CLIENT_SECRET');
$siteHostname = getenv('GRAPH_SITE_HOSTNAME');
$sitePath     = getenv('GRAPH_SITE_PATH');
$itemId       = getenv('GRAPH_ITEM_ID');

echo "=== CONFIGURACIÓN ===\n";
echo "Tenant ID : $tenantId\n";
echo "Client ID : $clientId\n";
echo "Secret    : " . substr($clientSecret, 0, 6) . "...\n";
echo "Hostname  : $siteHostname\n";
echo "Site Path : $sitePath\n";
echo "Item ID   : $itemId\n\n";

// 1. Obtener token
echo "=== PASO 1: Obtener Access Token ===\n";
$tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
$body = http_build_query([
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'scope'         => 'https://graph.microsoft.com/.default',
    'grant_type'    => 'client_credentials',
]);
$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$tokenData = json_decode($resp, true);
if (!empty($tokenData['access_token'])) {
    $token = $tokenData['access_token'];
    echo "OK - Token obtenido (expira en {$tokenData['expires_in']}s)\n\n";
} else {
    echo "FALLO HTTP $code:\n$resp\n";
    exit;
}

// Helper
function graphGet(string $url, string $token): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Accept: application/json"]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => json_decode($resp, true), 'raw' => $resp, 'err' => $err];
}

// 2. Obtener siteId
echo "=== PASO 2: Resolver Site ID ===\n";
$siteUrl = "https://graph.microsoft.com/v1.0/sites/{$siteHostname}:{$sitePath}";
echo "URL: $siteUrl\n";
$r = graphGet($siteUrl, $token);
echo "HTTP: {$r['code']}\n";
if ($r['code'] === 200 && !empty($r['body']['id'])) {
    $siteId = $r['body']['id'];
    echo "OK - Site ID: $siteId\n\n";
} else {
    echo "FALLO:\n" . json_encode($r['body'], JSON_PRETTY_PRINT) . "\n";
    echo "\n*** PROBABLE CAUSA: Falta permiso Sites.Read.All en Azure Portal ***\n";
    exit;
}

// 3. Obtener drive del item
echo "=== PASO 3: Resolver Drive ID ===\n";
$itemUrl = "https://graph.microsoft.com/v1.0/sites/{$siteId}/drive/items/{$itemId}";
echo "URL: $itemUrl\n";
$r = graphGet($itemUrl, $token);
echo "HTTP: {$r['code']}\n";
if ($r['code'] === 200 && !empty($r['body']['parentReference']['driveId'])) {
    $driveId = $r['body']['parentReference']['driveId'];
    echo "OK - Drive ID: $driveId\n\n";
} else {
    echo "FALLO:\n" . json_encode($r['body'], JSON_PRETTY_PRINT) . "\n";
    exit;
}

// 4. Listar hojas del workbook
echo "=== PASO 4: Listar hojas del workbook ===\n";
$wsUrl = "https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$itemId}/workbook/worksheets";
$r = graphGet($wsUrl, $token);
echo "HTTP: {$r['code']}\n";
if ($r['code'] === 200) {
    echo "OK - Hojas encontradas:\n";
    foreach (($r['body']['value'] ?? []) as $ws) {
        echo "  - {$ws['name']}\n";
    }
} else {
    echo "FALLO:\n" . json_encode($r['body'], JSON_PRETTY_PRINT) . "\n";
}
