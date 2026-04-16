<?php
namespace Requerimiento;

class ExcelGraphAdapter
{
    private $clientId;
    private $clientSecret;
    private $tenantId;
    private $redirectUri;
    private $excelFilename;
    private $worksheetName;
    private $onedriveFileUrl;
    private $tokenStoragePath;
    private $cachedItemId = null;  // caché para evitar 2 llamadas API por operación

    public function __construct(array $config = [])
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Cargar .env si las variables aún no están disponibles
        if (empty($_ENV['AZURE_CLIENT_ID']) && empty(getenv('AZURE_CLIENT_ID'))) {
            if (class_exists('\Dotenv\Dotenv')) {
                $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
                $dotenv->safeLoad();
            }
        }

        $this->clientId         = $config['client_id']      ?? $_ENV['AZURE_CLIENT_ID']      ?? getenv('AZURE_CLIENT_ID');
        $this->clientSecret     = $config['client_secret']  ?? $_ENV['AZURE_CLIENT_SECRET']  ?? getenv('AZURE_CLIENT_SECRET');
        $this->tenantId         = $config['tenant_id']      ?? $_ENV['AZURE_TENANT_ID']      ?? getenv('AZURE_TENANT_ID');
        $this->redirectUri      = $config['redirect_uri']   ?? $_ENV['GRAPH_REDIRECT_URI']   ?? getenv('GRAPH_REDIRECT_URI');
        $this->excelFilename    = trim($config['excel_filename'] ?? $_ENV['EXCEL_FILENAME']   ?? getenv('EXCEL_FILENAME') ?? '', '"');
        $this->worksheetName    = trim($config['worksheet_name'] ?? $_ENV['WORKSHEET_NAME']   ?? getenv('WORKSHEET_NAME') ?? '', '"');
        $this->onedriveFileUrl  = $config['onedrive_file_url'] ?? $_ENV['ONEDRIVE_FILE_URL']  ?? getenv('ONEDRIVE_FILE_URL');
        $this->tokenStoragePath = __DIR__ . '/../storage/graph_token.json';
    }

    // ── Manejo de sesión y tokens ────────────────────────────────────────────

    public function getAuthorizationUrl(): string
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        return "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/authorize?" . http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => 'Files.ReadWrite offline_access',
            'state'         => $state,
        ]);
    }

    public function handleCallback(): bool
    {
        if ($_REQUEST['state'] !== ($_SESSION['oauth_state'] ?? '')) {
            throw new \Exception('Invalid state parameter');
        }

        if (empty($_REQUEST['code'])) {
            throw new \Exception('No authorization code received');
        }

        $code = $_REQUEST['code'];
        $tokenData = $this->exchangeCodeForToken($code);

        $expiresAt = time() + ($tokenData['expires_in'] - 60);

        $_SESSION['oauth_access_token']  = $tokenData['access_token'];
        $_SESSION['oauth_refresh_token'] = $tokenData['refresh_token'] ?? null;
        $_SESSION['oauth_expires_at']    = $expiresAt;

        // Guardar token de forma persistente para todos los usuarios
        $this->saveTokenToStorage([
            'access_token'  => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_at'    => $expiresAt,
        ]);

        return true;
    }

    private function exchangeCodeForToken(string $code): array
    {
        $url  = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        $body = http_build_query([
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
            'scope'         => 'Files.ReadWrite offline_access',
        ]);

        $response = $this->curlRequest('POST', $url, [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], $body);

        if (empty($response['access_token'])) {
            throw new \Exception('Failed to get access token: ' . json_encode($response));
        }

        return $response;
    }

    public function getAccessToken(): string
    {
        // Si la sesión no tiene token, intentar cargar desde el almacén del servidor
        if (empty($_SESSION['oauth_access_token'])) {
            $stored = $this->loadTokenFromStorage();
            if ($stored) {
                $_SESSION['oauth_access_token']  = $stored['access_token'];
                $_SESSION['oauth_refresh_token'] = $stored['refresh_token'] ?? null;
                $_SESSION['oauth_expires_at']    = $stored['expires_at'] ?? 0;
            }
        }

        if (empty($_SESSION['oauth_access_token'])) {
            throw new \Exception('No hay token de Microsoft. Por favor autentique en /auth.php');
        }

        // Renovar si está por vencer (5 minutos de margen)
        if ($_SESSION['oauth_expires_at'] < time() + 300 && !empty($_SESSION['oauth_refresh_token'])) {
            $this->refreshAccessToken();
        }

        return $_SESSION['oauth_access_token'];
    }

    public function hasStoredToken(): bool
    {
        $stored = $this->loadTokenFromStorage();
        return !empty($stored['refresh_token']);
    }

    public function hasValidToken(): bool
    {
        $stored = $this->loadTokenFromStorage();
        if (empty($stored['refresh_token'])) {
            return false;
        }
        // Considerar expirado si ya venció o vence en menos de 5 minutos
        $expiresAt = $stored['expires_at'] ?? 0;
        return $expiresAt > (time() + 300);
    }

    private function saveTokenToStorage(array $tokenData): void
    {
        $dir = dirname($this->tokenStoragePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        file_put_contents($this->tokenStoragePath, json_encode($tokenData), LOCK_EX);
        @chmod($this->tokenStoragePath, 0600);
    }

    private function loadTokenFromStorage(): ?array
    {
        if (!file_exists($this->tokenStoragePath)) {
            return null;
        }
        $data = json_decode(file_get_contents($this->tokenStoragePath), true);
        return is_array($data) ? $data : null;
    }

    private function refreshAccessToken(): void
    {
        $url  = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
        $body = http_build_query([
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $_SESSION['oauth_refresh_token'],
            'grant_type'    => 'refresh_token',
            'scope'         => 'Files.ReadWrite offline_access',
        ]);

        $response = $this->curlRequest('POST', $url, [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ], $body);

        if (empty($response['access_token'])) {
            throw new \Exception('Failed to refresh token: ' . json_encode($response));
        }

        $expiresAt = time() + ($response['expires_in'] - 60);

        $_SESSION['oauth_access_token']  = $response['access_token'];
        $_SESSION['oauth_refresh_token'] = $response['refresh_token'] ?? $_SESSION['oauth_refresh_token'];
        $_SESSION['oauth_expires_at']    = $expiresAt;

        // Actualizar almacén del servidor
        $this->saveTokenToStorage([
            'access_token'  => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? $_SESSION['oauth_refresh_token'],
            'expires_at'    => $expiresAt,
        ]);
    }

    // ── cURL HTTP Helper ─────────────────────────────────────────────────────

    private function curlRequest(string $method, string $url, array $headers = [], $body = null): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $builtHeaders = [];
        foreach ($headers as $k => $v) {
            $builtHeaders[] = "$k: $v";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $builtHeaders);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \Exception("cURL error: $err");
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $msg = $data['error']['message'] ?? json_encode($data);
            $code = $data['error']['code'] ?? '';
            throw new \Exception("Graph API error $httpCode [$code]: $msg");
        }

        return $data ?? [];
    }

    // ── Resolución de archivo ────────────────────────────────────────────────

    private function resolveWorkbookItemId(): string
    {
        if ($this->cachedItemId !== null) {
            return $this->cachedItemId;
        }

        $token = $this->getAccessToken();

        // Usar la URL compartida de OneDrive si está disponible (más confiable)
        if (!empty($this->onedriveFileUrl)) {
            $b64 = base64_encode($this->onedriveFileUrl);
            $b64 = rtrim($b64, '=');
            $b64 = strtr($b64, '+/', '-_');
            $shareId = 'u!' . $b64;

            $item = $this->curlRequest('GET', "https://graph.microsoft.com/v1.0/shares/{$shareId}/driveItem", [
                'Authorization' => "Bearer $token",
                'Accept'        => 'application/json',
            ]);
        } else {
            // Fallback: buscar por nombre en el drive del usuario
            $encodedPath = rawurlencode($this->excelFilename);
            $item = $this->curlRequest('GET', "https://graph.microsoft.com/v1.0/me/drive/root:/{$encodedPath}", [
                'Authorization' => "Bearer $token",
                'Accept'        => 'application/json',
            ]);
        }

        if (empty($item['id'])) {
            throw new \Exception("No se encontró el archivo Excel en OneDrive: " . json_encode($item));
        }

        $this->cachedItemId = $item['id'];
        return $this->cachedItemId;
    }

    // ── Métodos públicos para Excel ──────────────────────────────────────────

    public function getRangeValues(string $worksheetName, string $address): array
    {
        $token  = $this->getAccessToken();
        $itemId = $this->resolveWorkbookItemId();
        $url    = "https://graph.microsoft.com/v1.0/me/drive/items/{$itemId}/workbook/worksheets/" . rawurlencode($worksheetName) . "/range(address='" . rawurlencode($address) . "')";

        $data = $this->curlRequest('GET', $url, [
            'Authorization' => "Bearer $token",
            'Accept'        => 'application/json',
        ]);

        return $data['values'] ?? [];
    }

    public function findLastDataRow(string $worksheetName): int
    {
        // Usa usedRange para obtener solo la dirección del último rango usado ─ mínimo tráfico de red
        $token  = $this->getAccessToken();
        $itemId = $this->resolveWorkbookItemId();
        $url    = "https://graph.microsoft.com/v1.0/me/drive/items/{$itemId}/workbook/worksheets/"
                . rawurlencode($worksheetName) . '/usedRange(valuesOnly=true)?$select=address,rowCount';

        try {
            $data = $this->curlRequest('GET', $url, [
                'Authorization' => "Bearer $token",
                'Accept'        => 'application/json',
            ]);
            // address tiene formato "Hoja!A1:S2196" o "Hoja!$A$1:$S$2196"
            if (!empty($data['address'])) {
                if (preg_match('/[A-Z]+\$?(\d+)\s*$/', $data['address'], $m)) {
                    return max(3, (int)$m[1]); // mínimo 3 para que los datos empiecen en fila 4
                }
            }
        } catch (\Exception $e) {
            // hoja vacía ─ ok
        }

        return 3; // predeterminado: después de título + vacío + encabezados
    }

    public function appendRowToWorksheet(string $worksheetName, array $rowValues): array
    {
        $last    = $this->findLastDataRow($worksheetName);
        $next    = $last + 1;
        $address = "A{$next}:S{$next}";

        $rowValues = array_slice(array_pad($rowValues, 19, ''), 0, 19);

        return $this->writeRange($worksheetName, $address, $rowValues);
    }

    public function updateRowInWorksheet(string $worksheetName, int $rowNumber, array $rowValues): array
    {
        $address   = "A{$rowNumber}:S{$rowNumber}";
        $rowValues = array_slice(array_pad($rowValues, 19, ''), 0, 19);

        return $this->writeRange($worksheetName, $address, $rowValues);
    }

    private function writeRange(string $worksheetName, string $address, array $rowValues): array
    {
        $token  = $this->getAccessToken();
        $itemId = $this->resolveWorkbookItemId();
        $url    = "https://graph.microsoft.com/v1.0/me/drive/items/{$itemId}/workbook/worksheets/" . rawurlencode($worksheetName) . "/range(address='" . rawurlencode($address) . "')";

        $headers = [
            'Authorization' => "Bearer $token",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
        $body = json_encode(['values' => [$rowValues]]);

        // Reintentar hasta 4 veces si el archivo está bloqueado (409)
        $maxAttempts = 4;
        $lastException = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->curlRequest('PATCH', $url, $headers, $body);
            } catch (\Exception $e) {
                $lastException = $e;
                // Solo reintentar en error 409 (archivo bloqueado)
                if (strpos($e->getMessage(), 'Graph API error 409') === false) {
                    throw $e;
                }
                if ($attempt < $maxAttempts) {
                    sleep(2); // esperar 2s antes de reintentar
                }
            }
        }
        throw new \Exception('El archivo Excel está abierto en Excel escritorio. Ciérralo y vuelve a intentar.');
    }

    public function getAllRows(string $worksheetName): array
    {
        try {
            return $this->getAllRowsOrFail($worksheetName);
        } catch (\Exception $e) {
            error_log("getAllRows error: " . $e->getMessage());
            return [];
        }
    }

    public function getAllRowsOrFail(string $worksheetName): array
    {
        // Usa usedRange para obtener solo las filas realmente utilizadas ─ no las 5000 vacías
        $token  = $this->getAccessToken();
        $itemId = $this->resolveWorkbookItemId();
        $url    = "https://graph.microsoft.com/v1.0/me/drive/items/{$itemId}/workbook/worksheets/"
                . rawurlencode($worksheetName) . '/usedRange(valuesOnly=true)';

        $data = $this->curlRequest('GET', $url, [
            'Authorization' => "Bearer $token",
            'Accept'        => 'application/json',
        ]);
        return $data['values'] ?? [];
    }

    // Escribe el título y encabezados de columna (ejecutar una sola vez al inicializar el archivo)
    public function initializeHeaders(string $worksheetName): void
    {
        $headers = [
            'Turno', 'Fecha', 'Requerimiento', 'Solicitante', 'Negocio',
            'Ambiente', 'Capa', 'Servidor', 'Estado', 'Tipo de Solicitud',
            'Ticket', 'Tipo de Pase a Prod y QA', 'IC', 'Cantidad',
            'Tiempo Total', 'Tiempo Unidad', 'Observaciones', 'ID', 'Registro',
        ];

        // Fila 1: título
        $this->writeRange($worksheetName, 'A1:A1', ['Pasos a Producción']);
        // Fila 3: encabezados de columna
        $this->writeRange($worksheetName, 'A3:S3', $headers);
    }

    /**
     * Escribir una fila de datos desde la BD a Excel
     * Usado por el background sync
     */
    public function writeRowFromDb(string $worksheetName, int $excelRow, array $dbRow): void
    {
        $rowValues = [
            $dbRow['turno'] ?? '',
            $dbRow['fecha'] ?? '',
            $dbRow['requerimiento'] ?? '',
            $dbRow['solicitante'] ?? '',
            $dbRow['negocio'] ?? '',
            $dbRow['ambiente'] ?? '',
            $dbRow['capa'] ?? '',
            $dbRow['servidor'] ?? '',
            $dbRow['estado'] ?? '',
            $dbRow['tipo_solicitud'] ?? '',
            $dbRow['numero_ticket'] ?? '',
            $dbRow['tipo_pase'] ?? '',
            $dbRow['ic'] ?? '',
            $dbRow['cantidad'] ?? '',
            $dbRow['tiempo_total'] ?? '',
            $dbRow['tiempo_unidad'] ?? '',
            $dbRow['observaciones'] ?? '',
            $dbRow['id'] ?? '',
            $dbRow['registro'] ?? '',
        ];

        $this->updateRowInWorksheet($worksheetName, $excelRow, $rowValues);
    }
}
