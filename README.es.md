# Jura

*[Read in English](README.md)*

Jura es una aplicación de finanzas personales para llevar el control de cuentas, ingresos, gastos, presupuestos, metas de ahorro y pagos recurrentes en varias monedas — construida con Laravel, Livewire y Volt.

## Funcionalidades

- **Cuentas** — bancos, billeteras digitales y efectivo, cada una con su propia moneda (por ejemplo COP y USD en paralelo, nunca mezcladas en los totales).
- **Transacciones** — ingresos, gastos y transferencias entre cuentas, con categorías y métodos de pago.
- **Importación de CSV** — importa en bloque transacciones de ingresos/gastos desde una hoja de cálculo, con validación por fila y detección de duplicados.
- **Presupuestos** — límites de gasto por categoría (mensuales o anuales), asociados a una moneda, con alertas por umbral.
- **Metas de ahorro** — montos objetivo con fecha límite opcional, seguimiento de aportes y un ritmo de ahorro mensual sugerido.
- **Transacciones recurrentes** — genera transacciones automáticamente según una frecuencia (semanal, quincenal, mensual, anual), con recordatorios de próximos pagos.
- **Dashboard e informes** — informes mensuales, anuales y de rango personalizado con desgloses por categoría/cuenta, exportación a PDF/CSV, e insights automáticos de gasto.
- **Notificaciones** — alertas de saldo bajo, umbral de presupuesto, próximo pago recurrente y meta de ahorro atrasada.
- **Consciente del multi-moneda** — cada agregado (patrimonio, informes, presupuestos) se agrupa por moneda en lugar de sumar monedas distintas entre sí.
- **Localización** — inglés por defecto, con español disponible como idioma secundario.

## Stack tecnológico

- **Backend**: PHP 8.3, Laravel 13
- **Frontend**: Livewire 3 + Volt (componentes de archivo único), Tailwind CSS, Alpine.js, ApexCharts
- **Base de datos**: MariaDB
- **Caché/sesiones**: Redis
- **Exportación a PDF**: barryvdh/laravel-dompdf
- **Entorno local**: Laravel Sail (Docker)

## Primeros pasos

Requiere Docker.

```bash
composer install
cp .env.example .env
php artisan sail:install # si Sail aún no está configurado
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

La aplicación corre en `http://localhost`. El seeder crea un usuario de prueba:

- **Email**: `test@example.com`
- **Contraseña**: `password`

## Comandos útiles

```bash
./vendor/bin/sail artisan migrate:fresh --seed   # reiniciar la base de datos
./vendor/bin/sail artisan test                   # ejecutar la suite de pruebas
./vendor/bin/sail npm run build                  # compilar los assets del frontend para producción
```

## Estructura del proyecto

- `app/Services` — lógica de negocio (registro de transacciones, saldos, presupuestos, informes, insights, importación de CSV), separada de los componentes Livewire.
- `app/Livewire` y `resources/views/livewire` — componentes de clase Livewire y componentes Volt de archivo único, uno por funcionalidad.
- `app/Policies` — autorización por modelo (cada operación de escritura está restringida al usuario autenticado).
- `database/migrations` — el esquema, incluyendo el soporte multi-moneda añadido sobre el esquema base.

## Localización

El locale de la aplicación (`APP_LOCALE`, por defecto `en`) determina las cadenas de texto traducidas mediante `lang/es.json`. Las cadenas de origen se escriben en inglés (`__('English text')`); el español está totalmente soportado como idioma secundario agregando la entrada correspondiente en `lang/es.json`. De forma independiente, cada usuario puede definir una preferencia de `locale` en **Preferencias** (inglés/español) que solo afecta el formato de números y fechas (por ejemplo `1,234.56` frente a `1.234,56`), no el idioma en el que se traduce el texto de la interfaz.
