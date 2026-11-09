# Convenciones de i18n

## Formatos de visualización
El idioma (`locale`) decide las palabras; las preferencias de visualización separadas (`date_format`, `time_format`, separadores,
`fraction_digits`, `currency_display`, `currency_position`, todas en "auto" por defecto) deciden cómo se ven las fechas y los
números. No formatees números ni fechas a mano: usa `useFormat()` en Vue y `$user->formatter()` (`UserFormat`) en PHP.

## Estructura

`lang/` son arrays PHP basados en directorios, no el antiguo `lang/es.json` plano:

```
lang/en/   accounts.php  buckets.php  categories.php  enums.php  insights.php
           messages.php  notifications.php  pdf.php  preferences.php
           planned-events.php  planner.php  recurring-transactions.php  savings-goals.php  transactions.php  wishlist.php
lang/es/   (mirrors lang/en/ exactly, same keys)
```

- Un archivo por módulo de dominio para las cadenas de flash/acciones (`__('budgets.saved')`, `__('categories.has_transactions')`), siguiendo el estilo de array plano de, por ejemplo, `lang/en/accounts.php`.
- `enums.php` — los métodos `label()` de cada enum del backend (`AccountType`, `CategoryType`, `TransactionType`, `PaymentMethod`, `RecurringFrequency`, `BudgetPeriodType`, `SavingsGoalStatus`) se resuelven mediante `__('enums.{enum_snake_case}.{value}')`.
- `messages.php` — cadenas transversales que no pertenecen a un solo dominio (encabezados de columnas CSV bajo `messages.csv.*`, mensajes de error de `TransactionImportService` bajo `messages.import.*`).
- `insights.php` — cada oración con plantilla que produce `InsightService`.
- `.env`: `APP_LOCALE=en`, `APP_FALLBACK_LOCALE=en` — el inglés es la fuente de verdad; el español es la traducción, no el idioma por defecto.

`APP_LOCALE` es el valor por defecto para los invitados. Para los usuarios con sesión iniciada, el middleware `SetUserLocale` (Shared, registrado antes de `HandleInertiaRequests`) llama a `App::setLocale()` con la preferencia `locale` guardada, de modo que las cadenas del servidor y las props `i18n` compartidas la siguen. Las páginas Vue todavía usan literales en inglés (ver abajo), así que cambiar el idioma solo afecta al texto del lado del servidor hasta que se migren a `$t()`.

## Puente servidor → cliente

`HandleInertiaRequests::share()` coloca las cadenas del idioma activo en cada respuesta de Inertia mediante
`UiMessages::for($locale)`: `lang/{locale}/messages.php` más cada archivo en `lang/{locale}/ui/` (un archivo por área,
expuesto como `ui.<file>.<key>`). El inglés completa cualquier clave que falte en español. `resources/js/app.js` arranca vue-i18n a partir de ello.

## Traducir páginas Vue

Las páginas y componentes Vue usan `$t('ui.<area>.<key>')` en las plantillas y `const { t } = useI18n()` en los scripts.

- Agrega la clave tanto a `lang/en/ui/<area>.php` como a `lang/es/ui/<area>.php` (`common.php` para palabras compartidas como Cancelar,
  `shell.php` para navegación, barra superior, notificaciones y barras de acciones masivas, y un archivo por área de página en los demás casos).
- Los placeholders usan las llaves de vue-i18n (`'Delete {label}?'`), no el `:label` de Laravel. Evita `@`, `|` y `{`/`}` como texto literal
  en un valor (son sintaxis de mensajes); los plurales usan `'one item | {count} items'` con `t(key, { count }, count)`.
- En español se dice "balde" para bucket.
- `tests/Feature/UiTranslationsTest.php` falla cuando `en` y `es` difieren en claves o cuando una página usa una clave que no existe.
  No puede ver texto en inglés que haya quedado escrito a mano en una plantilla, así que revisar una página implica hacer grep de literales.
- Todas las páginas y componentes están migrados. Los ejemplos de texto libre (placeholders) también están traducidos; los nombres que escriben los usuarios se muestran tal como se ingresaron.
