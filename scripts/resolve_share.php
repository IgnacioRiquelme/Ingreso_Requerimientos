<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$tenant = $_ENV['AZURE_TENANT_ID'] ?? null;
$clientId = $_ENV['AZURE_CLIENT_ID'] ?? null;
$clientSecret = $_ENV['AZURE_CLIENT_SECRET'] ?? null;
$shareUrl = $_ENV['GRAPH_DRIVE_URL'] ?? null;

if (!$tenant || !$clientId || !$clientSecret || !$shareUrl) {
    echo "Faltan variables de entorno. Asegúrate de tener en .env: AZURE_TENANT_ID, AZURE_CLIENT_ID, AZURE_CLIENT_SECRET, GRAPH_DRIVE_URL\n";
    exit(1);
}

$tokenUrl = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";
$http = new Client();
try {
    $res = $http->post($tokenUrl, [
        'form_params' => [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials'
        ],
        'headers' => ['Accept' => 'application/json']
    ]);
} catch (Exception $e) {
    echo "Error obteniendo token: " . $e->getMessage() . "\n";
    exit(1);
}

$body = json_decode((string)$res->getBody(), true);
$token = $body['access_token'] ?? null;
if (!$token) {
    echo "No se obtuvo access_token. Respuesta: " . json_encode($body) . "\n";
    exit(1);
}

function encodeShareId(string $url): string
{
    $b64 = base64_encode($url);
    $b64 = rtrim($b64, '=');
    $b64 = strtr($b64, '+/', '-_');
    return 'u!' . $b64;
}

$shareId = encodeShareId($shareUrl);
$graph = new Client(['base_uri' => 'https://graph.microsoft.com/v1.0/']);
try {
    $res = $graph->get("shares/{$shareId}/driveItem", [
        'headers' => [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json'
        ]
    ]);
    $data = json_decode((string)$res->getBody(), true);
    echo "Drive item resuelto:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    if (!empty($data['id'])) echo "item id: " . $data['id'] . "\n";
    if (!empty($data['parentReference']['driveId'])) echo "driveId: " . $data['parentReference']['driveId'] . "\n";
    if (!empty($data['parentReference']['siteId'])) echo "siteId: " . $data['parentReference']['siteId'] . "\n";
} catch (Exception $e) {
    echo "Error resolviendo share: " . $e->getMessage() . "\n";
    exit(1);
}

echo "OK\n";
