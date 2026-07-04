# Guía de Migración: Cambiar de OCR Remoto a Tesseract Local

Este documento te guiará paso a paso para migrar tu módulo ScanInvoices de un servicio OCR remoto (pago) a **Tesseract OCR local (gratis)**.

## 📋 Tabla de Contenidos

1. [Antes de Empezar](#antes-de-empezar)
2. [Opción A: Instalar Tesseract](#opción-a-instalar-tesseract)
3. [Opción B: Mantener Ambos Sistemas](#opción-b-mantener-ambos-sistemas)
4. [Configurar en ScanInvoices](#configurar-en-scaninvoices)
5. [Validar la Instalación](#validar-la-instalación)
6. [Solución de Problemas](#solución-de-problemas)
7. [Rollback (Volver al Sistema Remoto)](#rollback-volver-al-sistema-remoto)

---

## Antes de Empezar

### ¿Por qué cambiar?

| Aspecto | Tesseract (Local) | Servicio Remoto |
|--------|------------------|-----------------|
| **Costo** | 🟢 GRATIS | 🔴 Pago por llamada |
| **Límite de llamadas** | ♾️ Ilimitadas | 🔴 Limitado según plan |
| **Privacidad** | 🟢 En tu servidor | 🔴 Datos en terceros |
| **Velocidad** | ~2-10s/página | ~1-3s/página |
| **Configuración** | 🟢 Rápida | 🟢 Ya tienes |
| **Precisión** | 🟡 Buena | 🟢 Muy buena |

### Requisitos

- **Sistema Operativo:** Linux (Ubuntu/Debian) o similar
- **Acceso SSH:** Para instalar Tesseract
- **PHP:** 7.4+ (ya tienes en Dolibarr)
- **Permisos:** Usuario `www-data` u otro usuario web

---

## Opción A: Instalar Tesseract

### Paso 1: Instalar Tesseract OCR

#### En Ubuntu/Debian

```bash
# Actualizar lista de paquetes
sudo apt-get update

# Instalar Tesseract OCR
sudo apt-get install -y tesseract-ocr

# Instalar idiomas (Español + Inglés recomendado)
sudo apt-get install -y tesseract-ocr-spa
sudo apt-get install -y tesseract-ocr-eng

# (Opcional) Instalar Imagick para mejor procesamiento de imágenes
sudo apt-get install -y php-imagick imagemagick
```

#### En CentOS/RHEL

```bash
sudo yum install -y tesseract
sudo yum install -y tesseract-langpack-spa
sudo yum install -y php-pecl-imagick
```

#### En macOS

```bash
# Con Homebrew
brew install tesseract

# Idiomas adicionales
brew install tesseract-spa

# (Opcional) Imagick
brew install imagemagick
brew install php-imagick
```

### Paso 2: Verificar la Instalación

```bash
# Verificar que Tesseract está instalado
tesseract --version

# Debería mostrar algo como:
# tesseract 4.1.1
#  leptonica-1.80.0
#  libgif 5.2.1 : libjpeg 9d (libjpeg-turbo 2.0.6) : libpng 1.6.37 : libtiff 4.2.0 : zlib 1.2.11

# Verificar idiomas instalados
tesseract --list-langs

# Debería mostrar algo como:
# List of available languages (3):
# eng
# spa
# osd
```

### Paso 3: Verificar Permisos

```bash
# El usuario web debe poder ejecutar tesseract
ls -la /usr/bin/tesseract

# Debería mostrar algo como:
# -rwxr-xr-x 1 root root 123456 Jan 01 10:00 /usr/bin/tesseract

# Si el usuario www-data no puede ejecutar, añade permisos:
sudo chmod +x /usr/bin/tesseract
```

---

## Opción B: Mantener Ambos Sistemas

Si prefieres **mantener el servicio remoto como fallback**, puedes:

1. Instalar Tesseract local (sigue Opción A)
2. Configurar Tesseract como sistema principal
3. Si falla Tesseract, cambia a remoto manualmente en configuración

**Ventaja:** Puedes probar Tesseract sin perder el servicio remoto.

---

## Configurar en ScanInvoices

### Opción 1: Por GUI (Recomendado)

1. **En Dolibarr**, ve a:
   ```
   Inicio → Configuración → Módulos → ScanInvoices → Configuración
   ```

2. **Busca la sección "OCR"** y configura:

   ```
   Motor OCR:           Tesseract (Local, Free)
   Ruta de Tesseract:   tesseract
   Idioma OCR:          spa+eng
   ```

3. **Guarda los cambios**

### Opción 2: Directamente en Base de Datos (SQL)

```sql
-- Conectar a tu base de datos Dolibarr
-- Reemplaza 'dolibarr_db' con el nombre de tu base de datos

UPDATE llx_const 
SET value = 'tesseract' 
WHERE name = 'SCANINVOICES_OCR_TYPE' 
AND entity = 1;

UPDATE llx_const 
SET value = 'tesseract' 
WHERE name = 'SCANINVOICES_TESSERACT_PATH' 
AND entity = 1;

UPDATE llx_const 
SET value = 'spa+eng' 
WHERE name = 'SCANINVOICES_OCR_LANGUAGE' 
AND entity = 1;

-- Si las constantes no existen, créalas:
INSERT INTO llx_const (name, value, type, visible, entity, datemod) 
VALUES ('SCANINVOICES_OCR_TYPE', 'tesseract', 'chaine', 1, 1, NOW());

INSERT INTO llx_const (name, value, type, visible, entity, datemod) 
VALUES ('SCANINVOICES_TESSERACT_PATH', 'tesseract', 'chaine', 1, 1, NOW());

INSERT INTO llx_const (name, value, type, visible, entity, datemod) 
VALUES ('SCANINVOICES_OCR_LANGUAGE', 'spa+eng', 'chaine', 1, 1, NOW());
```

### Opción 3: En conf.php (Dolibarr)

Añade al archivo `conf/conf.php`:

```php
// OCR Configuration
$conf->global->SCANINVOICES_OCR_TYPE = 'tesseract';
$conf->global->SCANINVOICES_TESSERACT_PATH = 'tesseract';
$conf->global->SCANINVOICES_OCR_LANGUAGE = 'spa+eng';
```

---

## Validar la Instalación

### Test 1: Verificar Tesseract desde PHP

Crea un archivo `test_tesseract.php` en la raíz de Dolibarr:

```php
<?php
// test_tesseract.php

// Verificar si Tesseract está disponible
$output = [];
$returnVar = 0;
@exec('tesseract --version 2>&1', $output, $returnVar);

if ($returnVar === 0) {
    echo "<h3>✅ Tesseract está instalado</h3>";
    echo "<pre>" . implode("\n", $output) . "</pre>";
} else {
    echo "<h3>❌ Tesseract NO está disponible</h3>";
    echo "<p>Código de error: $returnVar</p>";
}

// Verificar idiomas
$output = [];
@exec('tesseract --list-langs 2>&1', $output, $returnVar);

echo "<h3>Idiomas disponibles:</h3>";
echo "<pre>" . implode("\n", $output) . "</pre>";
?>
```

Accede desde tu navegador:
```
https://tu-dolibarr.com/test_tesseract.php
```

### Test 2: Test desde ScanInvoices

1. En Dolibarr, ve a **ScanInvoices → Dashboard**
2. Busca el botón **"Test OCR"** (si existe en tu versión)
3. Debería mostrar: ✅ **OCR Engine: Tesseract (Local)**

### Test 3: Procesar una Factura

1. Carga una factura PDF en ScanInvoices
2. Dibuja los rectángulos para extraer datos
3. Presiona **"Run OCR"**
4. Verifica que el texto se extrae correctamente

---

## Parámetros de Configuración

### SCANINVOICES_OCR_TYPE

- **Valores:** `'tesseract'` o `'remote'`
- **Defecto:** `'remote'` (para compatibilidad)
- **Descripción:** Define qué motor OCR usar

```php
// Usar Tesseract local
$conf->global->SCANINVOICES_OCR_TYPE = 'tesseract';

// O usar servicio remoto
$conf->global->SCANINVOICES_OCR_TYPE = 'remote';
```

### SCANINVOICES_TESSERACT_PATH

- **Valores:** Ruta absoluta a tesseract (ej: `/usr/bin/tesseract` o simplemente `tesseract`)
- **Defecto:** `'tesseract'` (busca en PATH)
- **Descripción:** Ubicación del ejecutable tesseract

```php
// Automático (busca en PATH - recomendado)
$conf->global->SCANINVOICES_TESSERACT_PATH = 'tesseract';

// Ruta explícita (si no está en PATH)
$conf->global->SCANINVOICES_TESSERACT_PATH = '/usr/bin/tesseract';

// En Windows
$conf->global->SCANINVOICES_TESSERACT_PATH = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
```

### SCANINVOICES_OCR_LANGUAGE

- **Valores:** Códigos de idioma Tesseract, separados por `+`
- **Defecto:** `'spa+eng'` (Español + Inglés)
- **Descripción:** Idiomas para OCR

```php
// Solo Español
$conf->global->SCANINVOICES_OCR_LANGUAGE = 'spa';

// Español + Inglés (recomendado)
$conf->global->SCANINVOICES_OCR_LANGUAGE = 'spa+eng';

// Múltiples idiomas
$conf->global->SCANINVOICES_OCR_LANGUAGE = 'spa+eng+fra+deu';

// Códigos disponibles: eng, spa, fra, deu, ita, por, rus, zho, jpn...
```

---

## Solución de Problemas

### ❌ "tesseract: command not found"

**Causa:** Tesseract no está en PATH

**Solución:**

```bash
# Verificar dónde está tesseract
which tesseract

# Si devuelve nada, instálalo:
sudo apt-get install tesseract-ocr

# Si está instalado pero no en PATH, añade la ruta en config:
$conf->global->SCANINVOICES_TESSERACT_PATH = '/usr/bin/tesseract';
```

### ❌ "Error: Permission denied"

**Causa:** El usuario web no tiene permisos

**Solución:**

```bash
# Asegúrate que www-data puede ejecutar tesseract
sudo chmod +x /usr/bin/tesseract

# Verifica permisos
ls -la /usr/bin/tesseract
```

### ❌ OCR retorna texto vacío

**Causa:** Imagen de baja calidad o idioma incorrecto

**Solución:**

```php
// 1. Verifica que los idiomas están instalados
tesseract --list-langs

// 2. Si falta español, instálalo:
sudo apt-get install tesseract-ocr-spa

// 3. Prueba con imagen de mejor calidad
// 4. Aumenta el contraste del PDF si es necesario
```

### ❌ "Memory exhausted"

**Causa:** Tesseract usa mucha memoria con PDFs grandes

**Solución:**

```php
// En api.php o conf.php, aumenta memoria:
ini_set('memory_limit', '512M');
set_time_limit(300);
```

### ❌ OCR muy lento

**Causa:** Tesseract es más lento que servicios remotos

**Esperado:** Tesseract tarda 2-10 segundos por página

**Optimización:**

```php
// 1. Usa solo el idioma necesario:
$conf->global->SCANINVOICES_OCR_LANGUAGE = 'spa'; // No 'spa+eng'

// 2. Mejora la calidad del PDF escaneado

// 3. Aumenta recursos del servidor si es posible
```

---

## Rollback: Volver al Sistema Remoto

Si por cualquier razón quieres volver al servicio remoto:

### Opción 1: Por GUI

```
Dolibarr → Configuración → Módulos → ScanInvoices → Configuración

Motor OCR: Remote Service (Paid)
URL del Servicio: https://tu-servicio-ocr.com
```

### Opción 2: Por SQL

```sql
UPDATE llx_const 
SET value = 'remote' 
WHERE name = 'SCANINVOICES_OCR_TYPE' 
AND entity = 1;
```

### Opción 3: Por conf.php

```php
$conf->global->SCANINVOICES_OCR_TYPE = 'remote';
$conf->global->SCANINVOICES_URI = 'https://tu-servicio-ocr.com';
```

---

## Comparación: Antes vs Después

### Antes (OCR Remoto)

```
PDF cargado
    ↓
HTTP request → Servicio OCR remoto (pago)
    ↓
Respuesta JSON
    ↓
Datos extraídos en Dolibarr
```

**Coste:** $$ por factura
**Latencia:** Depende de internet
**Privacidad:** Datos en servidor tercero

### Después (Tesseract Local)

```
PDF cargado
    ↓
Tesseract (en tu servidor)
    ↓
Datos extraídos localmente
```

**Coste:** $0
**Latencia:** Rápido (en LAN)
**Privacidad:** 100% en tu control

---

## Estadísticas de Adopción

Con esta migración, esperarías:

| Métrica | Remoto | Tesseract |
|---------|--------|-----------|
| Coste anual (1000 PDFs) | $500-2000 | $0 |
| Tiempo procesamiento | 2-5s | 3-15s |
| Llamadas API | 1000 | 0 |
| Dependencia externa | Sí | No |

---

## Soporte Técnico

Si tienes problemas:

1. **Revisa los logs:**
   ```bash
   tail -f /var/log/dolibarr/dolibarr.log
   ```

2. **Prueba manualmente:**
   ```bash
   tesseract /ruta/a/imagen.jpg /tmp/output -l spa+eng
   cat /tmp/output.txt
   ```

3. **Contacta soporte** con:
   - Versión de Tesseract: `tesseract --version`
   - Versión de PHP: `php -v`
   - Error exacto del log
   - Captura de pantalla del panel

---

## Próximos Pasos

✅ Has completado la migración
- [ ] Instalar Tesseract
- [ ] Configurar en ScanInvoices
- [ ] Hacer test con una factura
- [ ] Verificar precisión de extracción
- [ ] Eliminar configuración remota (opcional)

---

**¿Preguntas? Revisa:**
- `INSTALLATION_TESSERACT.md` - Guía técnica de instalación
- `README.md` - Documentación general del módulo
- Logs de Dolibarr en `/var/log/dolibarr/`

¡Bienvenido al OCR libre y gratuito! 🚀
