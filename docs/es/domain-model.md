# Modelo de dominio

## Modelos

Los modelos viven en módulos: `Ledger` (Account, Category, Transaction), `Planning` (Bucket, Allocation, Project, WishlistItem, Scenario, PlannedEvent, PlannedEventSeries, SavingsGoal, SavingsGoalContribution, RecurringTransaction), `Identity` (User). Consulta `architecture.md` para las reglas de frontera.

| Modelo | Notas |
|---|---|
| `User` | `currency_default`, `locale`, `timezone` controlan el formato en toda la app (ver `preferences`). `onboarding_completed_at` es null hasta que el usuario termina o salta el asistente de configuración inicial; mientras sea null, el middleware `onboarded` (`EnsureOnboarded`) envía las visitas de páginas a `onboarding.show`. |
| `Account` | Banco/billetera/efectivo/tarjeta de crédito/ahorros. `initial_balance` + el libro de transacciones derivan el saldo en vivo: el saldo nunca es una columna almacenada (ver `BalanceCalculator`). `yield_rate` opcional (porcentaje anual) + `yield_compounding` (daily/monthly/yearly) modelan el interés solo sobre dinero real; la proyección de flujo de caja lo simula con el `Planning/Support/YieldSimulator` puro (nunca se almacena, nunca es una transacción). Soft-deletes; eliminar una cuenta con transacciones la archiva (`is_active = false`) en su lugar. |
| `Category` | Ingreso o gasto. `user_id` es nullable: `null` significa una categoría del sistema (sembrada, compartida por todos los usuarios). Usa un global scope `visibleToUser` **personalizado** (propietario O `user_id IS NULL`), no `BelongsToUser` — ver más abajo. Soft-deletes; las categorías del sistema y las categorías con transacciones no se pueden eliminar/editar (`Category::isEditable()`). |
| `BucketCycle` | Un ciclo de un bucket. El abierto solo lleva un `cap_override` por ciclo; uno cerrado es una instantánea inmutable (`opening`, `filled`, `moved_in`, `moved_out`, `spent`, `released`, `closing`, `cap`) escrita por `BucketCycleService`. |
| `Bucket` | Un apartado virtual de dinero (`spending` o `savings`), que reemplaza a `Budget`. Los buckets de gasto siguen una `category` contra un `cap_amount` por `cycle` (monthly/yearly) exactamente como lo hacían los presupuestos; los buckets de ahorro contienen dinero financiado con una meta opcional. `fill_rule` opcional (fixed/percent/of_remaining/fill_to_target; `fixed` es por ciclo cuando el bucket tiene un ciclo, contando el ingreso ya recibido en él mediante `BucketLedger::receivedInCycle`; `of_remaining` es una parte del ingreso aún no reclamado) + `fill_value` reclaman ingresos en orden de `priority`; `carry_over` (carry/release/overflow) decide qué ocurre con el sobrante cuando termina un ciclo; `cycle_goal` (solo ahorro: lo que aspira a recibir cada ciclo), `cycle` (daily/weekly/monthly/yearly) + `cycle_every` + `cycle_anchor` opcional definen la ventana repetitiva (`Support/CyclePeriod` puro; los días de fin de mes se ajustan); `step_up_value` + `step_up_after_bucket_id` (un valor de relleno que aplica desde el ciclo posterior a que otro bucket alcance su meta o se complete; `BucketLedger::fillValueFor`), `completed_on` + `completes_on_purchase` (un bucket completado no recibe ingresos; `BucketLedger::complete/reopen` mueven lo que queda al bucket de overflow/por defecto, y comprar su ítem de la lista de deseos lo completa, también en el motor de pronóstico); `allowed_account_ids` restringe los ingresos de qué cuentas pueden llenarlo; un bucket `is_default` por moneda captura el ingreso no reclamado. Único por usuario/categoría/moneda/ciclo. |
| `Allocation` | Una línea con signo del libro de dinero virtual (`income`, `planned`, `transfer`, `carry`, `spend`, `adjustment`). El saldo financiado de un bucket es la suma de sus asignaciones; para un bucket de gasto vinculado a una categoría, el *disponible* además resta el gasto real desde `tracking_started_on`. |
| `SavingsGoal` | Monto meta + fecha meta opcional. `status`: active/completed/archived. Soft-deletes. Tiene muchos `SavingsGoalContribution`; registrar una contribución que alcanza la meta cambia automáticamente el estado a completed. |
| `SavingsGoalContribution` | Pertenece a un `SavingsGoal`; opcionalmente vinculada a la `Transaction` de la que provino (`linked_transaction_id`, nullable). |
| `RecurringTransaction` | Plantilla para transacciones autogeneradas (weekly/biweekly/monthly/yearly). `is_active` activa/desactiva sin eliminar. |
| `Project` / `WishlistItem` | Un `Project` (project/hobby/category/other) agrupa ítems de la lista de deseos. Un `WishlistItem` tiene un precio, `importance` 1-5, `desired_by` opcional, `bucket_id` (un bucket de ahorro de la misma moneda), `allowed_account_ids` y un `status` (wanted/purchased/dropped). Comprar registra un gasto real mediante `RecordsTransactions::record` más una `Allocation` `spend` negativa (limitada a los fondos disponibles del bucket) y guarda ambos ids para poder deshacer la compra en conjunto (`WishlistService`). |
| `Scenario` | Un orden de compra guardado para el planificador: un nombre, moneda, `forecast_view` (conservative/expected/optimistic), `horizon_months` (12/24/36) y `ScenarioLine`s ordenadas (`wishlist_item_id`, `position`, `not_before` opcional). Los resultados nunca se almacenan; se recalculan bajo demanda. |
| `PlannedEvent` | Un ingreso o gasto proyectado en una fecha futura (`planned`/`realized`/`skipped`) con una `certainty` (confirmed/expected/speculative). **Nunca toca los saldos.** Realizar uno registra una `Transaction` real mediante `Ledger\Contracts\RecordsTransactions` y guarda su id en `realized_transaction_id`. Opcionalmente es parte de un `PlannedEventSeries` (una regla repetitiva que materializa ocurrencias hasta 12 meses por adelantado; extendida diariamente por `planning:extend-series`). Eliminar una ocurrencia hace hard-delete de la fila y recuerda su fecha en `excluded_dates` de la serie, de modo que al extender nunca se vuelve a crear; un evento `skipped` es una acción visible del usuario que se puede reabrir. Reemplaza al antiguo `BudgetForecast`. |
| `Transaction` | `type`: income / expense / transfer_out / transfer_in. Una transferencia siempre son **dos filas** que comparten un `transfer_group_id` (ver `TransactionService::createTransfer`): nunca una sola fila con una columna de "cuenta destino". `category_id` es null solo en las filas de transferencia. Soft-deletes. |

Eventos entre módulos desde `Ledger`: `TransactionsMutated`, `IncomeRecorded`, `IncomeRemoved`.

Todos los modelos excepto `Category` (y el `SavingsGoalContribution`, que solo es de unión) usan el trait `App\Modules\Shared\Concerns\BelongsToUser`.

## `BelongsToUser`

```php
trait BelongsToUser
{
    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope('user', fn (Builder $builder) =>
            Auth::check() && $builder->where($builder->getModel()->getTable().'.user_id', Auth::id()));

        static::creating(fn ($model) => $model->user_id ??= Auth::id());
    }
}
```

Toda consulta contra un modelo `BelongsToUser` queda limitada de forma transparente al usuario autenticado, incluido el **route-model binding implícito**. Esto tiene una consecuencia que aparece en todos los controladores y sus tests: **un usuario autenticado que solicita el recurso de otro usuario por ID obtiene un 404, no un 403**: la fila es invisible para la consulta antes de que se ejecute siquiera una Policy, no simplemente está prohibida. Solo el scope personalizado de `Category` (propietario o sistema) y los casos en que una regla `exists:` de un Form Request omite por completo los scopes de Eloquent (ver más abajo) se desvían de esto.

Usa `Model::forUser($user)` (también del trait) para consultar fuera de un contexto de petición (jobs, comandos de consola) donde `Auth::check()` es false.

## La validación debe re-acotar las reglas `exists:`

`exists:accounts,id` / `exists:categories,id` en un Form Request verifica **solo que la fila exista**, no que pertenezca al usuario actual: la regla `exists` de Laravel ejecuta una consulta cruda, omitiendo por completo el global scope `BelongsToUser`. Todo Form Request que acepta una clave foránea `account_id` o `category_id` la acota explícitamente:

```php
'account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('user_id', $this->user()->id)],
'category_id' => ['required', 'integer', Rule::exists('categories', 'id')
    ->where(fn ($query) => $query->where('user_id', $this->user()->id)->orWhereNull('user_id'))],
```

(Las categorías además permiten `user_id IS NULL`: las categorías del sistema son válidas para todos.) Si agregas un nuevo Form Request con una clave foránea hacia un modelo `BelongsToUser`, acótala de la misma manera: un `exists:table,id` sin acotar es una fuga de datos entre inquilinos, no solo un detalle de estilo. Esta misma brecha existía en la app Livewire original y solo se detectó al escribir los tests de feature de la Fase 8 (ver `testing-conventions.md`).

## Servicios clave (dentro de sus módulos)

- **`TransactionService`** (Ledger) — el único camino de código autorizado para escribir una `Transaction`. Es dueño de la partida doble de las transferencias (`createTransfer`/`updateTransfer`/`delete` mantienen ambas patas de una transferencia sincronizadas o las eliminan juntas) y despacha `TransactionsMutated` para que las cachés se invaliden.
- **`BalanceCalculator`** (Ledger) — deriva los saldos de cuentas y el patrimonio neto a partir de `initial_balance` + el libro, con caché (Redis, TTL de 1h, con clave por cuenta/por usuario) porque se lee en casi todas las páginas. La caché se invalida mediante `forgetAccount()`, llamado después de cualquier mutación que pueda cambiar un saldo.
- **`PlannedEventService`** (Planning) — crea eventos/series, edita y elimina con alcance `this`/`future`, realiza, omite y vincula planes, y sugiere transacciones reales coincidentes. `CashFlowProjector` (Planning) proyecta saldos por mes y moneda para un `ForecastView` (conservative = solo ingresos confirmados, expected = + esperados, optimistic = todos; los gastos siempre cuentan).
- **`PlannerService`** (Planning) — construye la entrada del motor a partir de datos reales (fondos `available` de los buckets, ingresos planificados abiertos filtrados por el `ForecastView`, ítems de la lista de deseos con `blocked` = el ítem y las cuentas del bucket no se solapan) y presenta los resultados. La matemática vive en clases puras bajo `Planning/Support/Forecast`: `ForecastEngine` reproduce los ingresos a través del `Waterfall`, aplica el carry-over de fin de ciclo y compra las líneas del escenario estrictamente en orden en la fecha más temprana en que su bucket puede pagar; `ScenarioSuggester` puntúa los ordenamientos (días de espera ponderados por importancia, una penalización por ítems no comprados dentro del horizonte y por retraso) y prueba todas las permutaciones hasta 6 ítems, luego búsqueda local.
- **`PlanningReportService`** (Reporting) — el único lugar donde se encuentran el historial registrado (`ReportService`) y las proyecciones (contratos de Planning); los mantiene bajo claves separadas para que una página nunca mezcle números reales y proyectados.
- **`BucketService`** (Planning) — seguimiento de límites para los buckets de gasto (`progress()`: gastado/restante/porcentaje/estado on_track/near_limit/exceeded para el ciclo actual). `BucketLedger` es el libro de asignaciones (saldo, disponible, crédito, mover, bucket por defecto); `BucketManager` guarda/elimina buckets manteniendo los invariantes; `IncomeAllocator` ejecuta el `Waterfall` puro cuando se dispara un evento `IncomeRecorded` de Ledger (solo si algún bucket tiene una regla; los ingresos planificados pueden llevar overrides explícitos por bucket); `BucketCycleService` (`planning:close-bucket-cycles` diario) cierra cada ciclo terminado en orden (poniéndose al día con los omitidos), escribe la instantánea `BucketCycle` y aplica el carry-over; `CycleProjector` calcula el relleno *provisional* del ciclo actual a partir del ingreso planificado abierto (nunca se almacena) y el overflow esperado al final; `Bucket::effectiveCap()` respeta un límite establecido para un solo ciclo.
- **`ReportService`** (Reporting) — la capa de agregación detrás del dashboard y las tres páginas de reportes (mensual/anual/personalizado): totales del período, gastos por categoría, gastos por cuenta, evolución del patrimonio neto, etc. Todo agregado es una `Collection` **indexada por moneda**: los montos en monedas distintas nunca se suman entre sí.
- **`InsightService`** (Reporting) — convierte los números de `ReportService` en oraciones cortas y traducidas ("Gastaste un 12% más que el mes pasado"). Cada frase es una llamada `__()` parametrizada sobre agregados reales, nunca texto libre.
- **`TransactionImportService`** (Ledger) — analiza/valida/importa un CSV de filas de ingresos/gastos. Nunca escribe una `Transaction` directamente; entrega las filas validadas a `TransactionService` para que las transacciones creadas por importación sigan exactamente el mismo camino que las creadas manualmente.
- **`RecurringTransactionService`** (Planning) — genera las `Transaction`s pendientes a partir de las plantillas `RecurringTransaction` activas (ver los comandos de consola `notify:*`).

## Dinero

`App\Modules\Shared\Support\Money` es un objeto de valor inmutable respaldado por bcmath: los montos nunca se manipulan como floats de PHP. `App\Modules\Shared\Casts\AsMoney` es el cast de Eloquent usado en cada columna de dinero (`amount`, `initial_balance`, `target_amount`, ...): al leer el atributo se obtiene un `Money`, al escribir se acepta un `Money`, un string numérico o un float. **Los controladores deben llamar a `->toFloat()` (o `->toDecimalString()`) antes de poner un `Money` en una prop de Inertia o en una respuesta JSON**: Inertia no sabe cómo serializarlo.
