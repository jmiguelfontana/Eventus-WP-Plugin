# Eventus API

Plugin de WordPress que integra sitios corporativos con la plataforma UDITrace para consultar y mostrar dispositivos medicos en tiempo real. Proporciona formularios accesibles, exportacion de datos y fichas detalladas listas para incrustar en cualquier pagina mediante shortcodes.

## Caracteristicas
- Configuracion centralizada de endpoints y credenciales de la API Eventus.
- Buscador AJAX con DataTables, filtros por columna y exportacion a Excel.
- Persistencia del estado de la tabla (termino, pagina, filtros) usando sessionStorage.
- Visualizacion de fichas completas de dispositivos con traducciones, metadatos y codigos de barras.
- Integracion con JsBarcode para renderizar codigos GS1 y HIBC sin dependencias adicionales.

## Requisitos
- WordPress 6.0 o superior.
- PHP 7.4 o superior con extensiones `curl` y `json`.
- Acceso a la API Eventus y una API Key valida (opcional pero recomendada).
- Conectividad saliente hacia los CDN de DataTables, Font Awesome, JsZip y JsBarcode (o sustituirlos por hosts propios).

## Instalacion
1. Copia el directorio `eventusapi` dentro de `wp-content/plugins/` o subelo desde el administrador de WordPress en ZIP.
2. Activa **Eventus API** desde el menu *Plugins*.
3. Tras la activacion se registran las opciones necesarias con valores por defecto.

## Configuracion
El plugin agrega una pagina en *Ajustes > Eventus API* donde puedes definir:
- **Endpoint de la API (busqueda)**: URL con placeholder `{ref}` o `{query}` para consultas por referencia.
- **Endpoint de la API (detalle por ID)**: URL con `{id}` o `{query}` para obtener la ficha del dispositivo.
- **API Key**: token opcional que se envia en cabeceras `Authorization: Bearer` y `X-API-KEY`.
- **Timeout**: tiempo maximo de espera en segundos (5-120) para las peticiones remotas.
- **Verificar SSL**: casilla para respetar certificados en produccion o desactivarlos en entornos de pruebas.

## Uso de shortcodes
Inserta los shortcodes en paginas o bloques de contenido:

```text
[eventus_search]
```
Renderiza el formulario de busqueda con DataTables y permite exportar resultados a Excel. Los datos se cargan via AJAX desde `admin-ajax.php` usando el nonce generado por el plugin.

```text
[eventus_device]
```
Muestra la ficha de un dispositivo tomando el parametro `id` de la URL (`/device/?id=1234`). El renderer organiza informacion basica, fabricante, nomenclaturas, identificadores y codigos de barras (SVG via JsBarcode).

## Recursos front-end
- **CSS**: `assets/buscador-api.css` define estilos para formularios y tablas.
- **JS**: `assets/buscador-api.js` gestiona DataTables, filtros persistentes y exportacion.
- **Codigos de barras**: `assets/device-barcode.js` invoca JsBarcode para crear SVG accesibles.
- **Logo**: coloca tu marca en `assets/logo.svg` o `assets/logo.png` para verlo en la pagina de ajustes.

Las librerias externas (DataTables, Buttons, Font Awesome, JsZip, JsBarcode) se cargan desde CDN por defecto. Si trabajas en entornos restringidos, registra esas dependencias encolandolas manualmente o sirvelas desde tu infraestructura.

## Flujo de datos
1. El usuario busca un termino mediante `[eventus_search]`.
2. WordPress procesa la llamada `admin-ajax.php?action=ba_datatables_search` y consulta el endpoint remoto configurado.
3. La respuesta JSON se normaliza (nombre, identifiers, fabricante...) y se entrega a DataTables.
4. Al seleccionar una fila, el enlace apunta a la pagina con `[eventus_device]`, que recupera el detalle y lo renderiza con plantillas HTML sanitizadas.

## Desarrollo
- Estructura principal:
  - `buscador-api.php`: bootstrap del plugin y registro de ganchos.
  - `includes/class-ba-admin-settings.php`: ajustes y sanitizacion de opciones.
  - `includes/class-ba-shortcode-search.php`: shortcode de busqueda y AJAX.
  - `includes/class-ba-shortcode-device.php`: shortcode de ficha y servicio asociado.
  - `includes/class-ba-device-service.php`: cliente para la API remota.
  - `includes/class-ba-device-renderer.php`: renderizado HTML y helper de traducciones.
  - `assets/`: recursos CSS/JS y logo.
- El codigo sigue la filosofia de mantener todo sanitizado con `esc_html`, `esc_attr` y `sanitize_text_field`.
- Los filtros `ba_request_args` permiten extender cabeceras o tiempos de espera segun el entorno.

## Consejos de despliegue
- Usa HTTPS en los endpoints y deja activa la verificacion SSL en produccion.
- Limita la API Key a los permisos estrictamente necesarios y almacena las credenciales fuera del repositorio cuando sea posible.
- Prueba en staging antes de publicar para confirmar que la API Eventus responde y que los CDN son accesibles desde tu hosting.

## Licencia
Este proyecto se distribuye bajo la licencia [Creative Commons Attribution-NonCommercial 4.0 International (CC BY-NC 4.0)](https://creativecommons.org/licenses/by-nc/4.0/).

Puedes reutilizar, adaptar y compartir el plugin siempre que cites a los autores originales y no lo utilices con fines comerciales. Consulta el texto completo de la licencia en el enlace anterior y añade el archivo `LICENSE` al repositorio para acompañar esta declaracion.
