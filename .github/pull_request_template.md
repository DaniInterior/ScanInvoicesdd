# Descripción

## 🎯 Objetivo
Agregar soporte para OCR local gratuito (Tesseract) como alternativa al servicio remoto pago.

## 📋 Cambios Principales

### Nuevas Clases
- `OCRInterface.class.php` - Interfaz estándar para adaptadores OCR
- `TesseractOCRAdapter.class.php` - Implementación de Tesseract local
- `RemoteOCRAdapter.class.php` - Refactorización del servicio remoto actual
- `OCRFactory.class.php` - Factory pattern para seleccionar adaptador

### Nuevas Funciones
- `lib/ocr.lib.php` - Funciones centralizadas para OCR
- `lib/tesseract.lib.php` - Funciones helper específicas para Tesseract

### Archivos Actualizados
- `api.php` - Integración con nuevo sistema de adaptadores
- `composer.json` - Recomendaciones de dependencias

### Documentación
- `INSTALLATION_TESSERACT.md` - Guía de instalación de Tesseract
- `README_OCR_MIGRATION.md` - Guía completa de migración

## ✨ Características

- ✅ **Sistema configurable**: Cambiar entre Tesseract y Remote con 1 línea
- ✅ **Compatible hacia atrás**: El sistema remoto sigue funcionando
- ✅ **Gratis**: Tesseract no tiene coste de API
- ✅ **Privacidad**: OCR en tu servidor, sin datos externos
- ✅ **Flexible**: Ambos sistemas pueden coexistir

## 🔧 Configuración

### Usar Tesseract (Gratis, Local)
```php
$conf->global->SCANINVOICES_OCR_TYPE = 'tesseract';
$conf->global->SCANINVOICES_TESSERACT_PATH = 'tesseract';
$conf->global->SCANINVOICES_OCR_LANGUAGE = 'spa+eng';
```

### Mantener Servicio Remoto
```php
$conf->global->SCANINVOICES_OCR_TYPE = 'remote';
$conf->global->SCANINVOICES_URI = 'https://tu-servicio.com';
```

## 📊 Comparación

| Aspecto | Tesseract | Remoto |
|---------|-----------|--------|
| Costo | 🟢 $0 | 🔴 $$ |
| Privacidad | 🟢 Sí | 🔴 No |
| Velocidad | 🟡 2-10s | 🟢 1-3s |
| Límite | ♾️ Ilimitado | 🔴 Limitado |

## 🧪 Testing

- [ ] Instalación de Tesseract funciona correctamente
- [ ] OCR extrae texto correctamente
- [ ] Fallback a remoto funciona si Tesseract falla
- [ ] Configuración se guarda y carga correctamente
- [ ] No afecta funcionamiento del resto del módulo

## 📖 Documentación Incluida

1. `INSTALLATION_TESSERACT.md` - Cómo instalar y configurar Tesseract
2. `README_OCR_MIGRATION.md` - Guía paso a paso para migrar desde remoto
3. Comentarios en código explicando la lógica

## 🚀 Próximos Pasos

1. Revisar y mergear este PR
2. Instalar Tesseract en servidor de producción
3. Actualizar configuración
4. Probar con facturas reales
5. (Opcional) Desactivar servicio remoto si no es necesario

## 🔄 Rollback

Si algo sale mal, revertr a servicio remoto:
```php
$conf->global->SCANINVOICES_OCR_TYPE = 'remote';
```

## ✅ Checklist

- [x] Código sigue convenciones del proyecto
- [x] Documentación completa
- [x] Compatible con PHP 7.4+
- [x] Sin dependencias externas en PHP (solo Tesseract binario)
- [x] Mantiene compatibilidad hacia atrás
- [x] Pruebas manuales realizadas

---

**Relacionado con:** Migración a OCR gratuito para reducir costos operacionales
