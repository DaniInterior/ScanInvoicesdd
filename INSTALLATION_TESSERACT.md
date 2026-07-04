# Instalación de Tesseract OCR

Esta guía te ayudará a instalar y configurar Tesseract como motor OCR gratuito para ScanInvoices.

## ¿Por qué Tesseract?

- ✅ **Completamente gratis** y de código abierto
- ✅ **Sin limitaciones de llamadas API**
- ✅ **Se ejecuta localmente** en tu servidor
- ✅ **Soporta múltiples idiomas** (español, inglés, etc.)
- ✅ **Buena precisión** en documentos escaneados de calidad media/alta

## Instalación

### Linux (Ubuntu/Debian)

```bash
# Instalar Tesseract
sudo apt-get update
sudo apt-get install tesseract-ocr

# Instalar soporte para idiomas adicionales
sudo apt-get install tesseract-ocr-spa  # Español
sudo apt-get install tesseract-ocr-eng  # Inglés

# (Opcional) Para mejor procesamiento de imágenes
sudo apt-get install php-imagick
sudo apt-get install imagemagick

# Verificar instalación
tesseract --version
```

### Linux (CentOS/RHEL)

```bash
sudo yum install tesseract
sudo yum install tesseract-langpack-spa
sudo yum install php-pecl-imagick
```

### macOS

```bash
# Con Homebrew
brew install tesseract

# Idiomas adicionales
brew install tesseract-spa  # Español

# Imagick para procesamiento de imágenes
brew install imagemagick
brew install php@8.1-imagick  # Ajusta la versión de PHP según la tuya
```

### Windows

1. Descarga el instalador desde: https://github.com/UB-Mannheim/tesseract/wiki
2. Ejecuta el instalador (recomendado: instalar en `C:\Program Files\Tesseract-OCR`)
3. Durante la instalación, selecciona los idiomas que necesites (Spanish, English)
4. Agrega a tu `php.ini`:
   ```ini
   extension=imagick
   ```

## Configuración en ScanInvoices

### 1. Configurar en el módulo

Ve a: **Setup → Modules → ScanInvoices → Settings** y configura:

```
OCR Engine Type:     Tesseract (Local, Free)
Tesseract Path:      tesseract  (o ruta completa si no está en PATH)
OCR Language:        spa+eng    (combina los idiomas que uses)
```

### 2. Variables de configuración (alternativa - en código)

Si prefieres configurar mediante SQL o constantes Dolibarr:

```php
// En conf.php o similar
$conf->global->SCANINVOICES_OCR_TYPE = 'tesseract';  // o 'remote' para usar el servicio remoto
$conf->global->SCANINVOICES_TESSERACT_PATH = 'tesseract';
$conf->global->SCANINVOICES_OCR_LANGUAGE = 'spa+eng';
```

### 3. Validar la instalación

En el panel de administración de ScanInvoices, hay un botón "Test OCR" que valida:
- ✅ Tesseract está instalado
- ✅ Ruta es correcta
- ✅ Permisos de archivo OK
- ✅ Idiomas instalados

## Códigos de idioma para Tesseract

```
eng     English
spa     Spanish
fra     French
deu     German
ita     Italian
por     Portuguese
rus     Russian
zho     Chinese
jpn     Japanese
```

Combínalos con `+`:
```
spa+eng       Español e Inglés
deu+eng       Alemán e Inglés
```

## Solución de problemas

### "tesseract: command not found"

**Solución:**
- Verifica que Tesseract esté instalado: `which tesseract`
- Si no está, reinstálalo
- O especifica la ruta completa en la configuración:
  ```
  SCANINVOICES_TESSERACT_PATH: /usr/bin/tesseract
  ```

### Error de permisos

```bash
# Asegúrate de que el usuario web puede ejecutar tesseract
sudo chmod +x /usr/bin/tesseract

# Verifica que el usuario www-data (o tu usuario web) tiene permisos
sudo usermod -a -G tesseract www-data
```

### Precisión baja en OCR

- **Asegúrate de que el PDF está bien escaneado** (no demasiado oscuro/claro)
- **Aumenta la calidad del escaneo** si es posible
- **Usa `spa` en lugar de `spa+eng`** si el documento es exclusivamente en español
- **Entrena Tesseract** con documentos similares (avanzado)

### Memoria insuficiente

Si tienes errores de memoria con PDFs grandes:

```php
// En api.php, aumenta los límites
ini_set('memory_limit', '512M');
set_time_limit(300);
```

## Cambiar entre Tesseract y servicio remoto

### Usar Tesseract (gratis, local)

```php
$conf->global->SCANINVOICES_OCR_TYPE = 'tesseract';
```

### Usar servicio remoto (pago)

```php
$conf->global->SCANINVOICES_OCR_TYPE = 'remote';
$conf->global->SCANINVOICES_URI = 'https://tu-servicio-ocr.com';
```

## Rendimiento

**Tesseract local:**
- Tiempo de procesamiento: ~2-10 segundos por página (depende del servidor)
- Sin costo de API
- Cero límite de llamadas

**Servicio remoto:**
- Tiempo de procesamiento: ~1-3 segundos por página
- Costo por llamada
- Límite de llamadas según plan

## Más información

- Documentación oficial de Tesseract: https://github.com/UB-Mannheim/tesseract/wiki
- Documentación de idiomas: https://github.com/tesseract-ocr/tesseract/wiki/Data-Files
- Mejora de precisión: https://tesseract-ocr.github.io/tessdoc/ImproveQuality.html
