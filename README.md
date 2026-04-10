Módulo "Requerimiento" - Esqueleto ligero

Este repositorio contiene un esqueleto ligero y utilidades para integrar el módulo `Requerimiento` y enviar registros a un Excel Online (SharePoint/OneDrive) usando Microsoft Graph.

Pasos rápidos:

- Copiar los controladores/vistas del módulo `Requerimiento` desde tu proyecto original dentro de `app/` y `resources/views/` de este esqueleto.
- Ajustar llamadas a BD para que usen `CsvComboboxAdapter` o directamente llamen a `ExcelGraphAdapter::appendRowToWorksheet`.
- Configurar variables de entorno en `.env`:

```
AZURE_TENANT_ID=de4b2eaa-9d3e-4e78-b82e-78263400a08d
AZURE_CLIENT_ID=5ed6716e-1c52-4e15-a962-f64a1717fcac
AZURE_CLIENT_SECRET=095074ae-401f-4304-9548-eecf80d04982
GRAPH_SITE_ID=               # opcional: site id de SharePoint
GRAPH_DRIVE_PATH=Clip%20GestionBCISeguros/Documentos%20compartidos/Requerimientos.xlsx # ruta relativa en drive
GRAPH_DRIVE_URL=            # (opcional) URL compartida de la hoja (ej: la URL que compartiste). Si está presente se resolverá automáticamente.
WORKSHEET_NAME=Requerimientos
```

Nota: ajusta `GRAPH_DRIVE_PATH` al path correcto de tu workbook en SharePoint. Puedes usar la API de Graph para localizar `siteId` y `driveItem` si lo prefieres.

Archivos clave:
- `src/ExcelGraphAdapter.php` — obtiene token via client credentials y escribe filas en la hoja.
- `src/CsvComboboxAdapter.php` — lectura/escritura simple de comboboxs desde/para `storage/data/*.csv`.
- `example_append.php` — ejemplo de uso para anexar una fila al workbook.

Instrucciones de uso rápido:

1. Instalar dependencias (requiere Composer):

```bash
composer install
```

2. Ajusta `.env` con tus credenciales Azure y la ruta del workbook.
2. Ajusta `.env` con tus credenciales Azure y la ruta del workbook. Puedes usar `GRAPH_DRIVE_URL` con la URL compartida.

3. Uso rápido (Windows PowerShell):

```powershell
.\run_dev.ps1
```

Esto ejecuta `composer install`, intenta resolver la URL compartida con `scripts/resolve_share.php` y levanta un servidor PHP en `http://localhost:8081`.

4. Prueba manual (si prefieres no usar el script):

```bash
composer install
php scripts/resolve_share.php
php -S localhost:8081 -t public
```

5. Abrir `http://localhost:8081/`, registrarse, e ingresar un requerimiento con el formulario.

6. También puedes ejecutar un ejemplo por línea de comandos:

```bash
php example_append.php
```
