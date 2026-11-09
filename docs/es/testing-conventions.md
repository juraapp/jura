# Convenciones de pruebas

Solo PHPUnit: **sin Pest**, por convención del proyecto. Cada clase de prueba extiende `Tests\TestCase` y usa `Illuminate\Foundation\Testing\RefreshDatabase`. Los nombres de método van en `snake_case` y empiezan con `test_`, no en camelCase anotado con `#[Test]`.

```bash
vendor/bin/sail artisan test --compact                              # suite completa
vendor/bin/sail artisan test --compact tests/Feature/BucketsTest.php # un archivo
vendor/bin/sail artisan test --compact --filter=test_user_can_create_a_budget
```

## Pruebas unitarias de JavaScript
`resources/js/format.js` está cubierto por el runner integrado de Node: `vendor/bin/sail npm run test:js` (archivos en
`tests/js`). Sin dependencias adicionales; mantén la lógica independiente del navegador en módulos simples para poder probarla de esta forma.

## Aserciones sobre respuestas de Inertia

```php
use Inertia\Testing\AssertableInertia as Assert;

$response = $this->actingAs($user)->get('/planning/budgets');

$response->assertOk();
$response->assertInertia(fn (Assert $page) => $page
    ->component('Buckets/Index')
    ->has('budgets', 2)          // conteo exacto
    ->where('typeFilter', 'all') // valor exacto
    ->has('currencies')          // solo presencia
);
```

Importa desde **`Inertia\Testing\AssertableInertia`**, no desde `Illuminate\Testing\Fluent\AssertableInertia`: esta última no existe para la versión de este paquete y produce un error de tipos en el sitio de la llamada a `assertInertia()`, no un error al importar, por lo que es fácil diagnosticarlo mal.

`component()` verifica que el archivo `.vue` indicado realmente exista en disco (`config('inertia.pages.testing.ensure_pages_exist')`, activado por defecto); esto es lo que hace posible la corrección de `pages.paths` en `config/inertia.php` (ver `architecture.md`).

## Pruebas de pertenencia: espera 404, no 403

Como casi todos los modelos usan `BelongsToUser` (ver `domain-model.md`), el propio route-model binding filtra la fila de otro usuario: la verificación `update`/`delete` de una policy ni siquiera se ejecuta en una petición genuinamente entre tenants, ya que el modelo nunca se encontró:

```php
public function test_user_cannot_update_another_users_bucket(): void
{
    $owner = User::factory()->create();
    $bucket = Bucket::factory()->for($owner)->create();
    $intruder = User::factory()->create();

    $response = $this->actingAs($intruder)->patch("/planning/buckets/{$bucket->id}", [...]);

    $response->assertNotFound(); // no assertForbidden()
}
```

`Category` es la excepción: su scope personalizado `visibleToUser` hace que una categoría de **sistema** (`user_id === null`) SEA visible para todos, así que un intruso que edita una categoría de sistema obtiene correctamente un 404→alcanzable por la policy→**403** (`isEditable()` devuelve false), mientras que editar la categoría *privada* de otro usuario sigue dando 404 (nunca es visible). No asumas 404 o 403 sin comprobar qué scope usa el modelo específico.

## Soft deletes

`Account`, `Category`, `SavingsGoal` y `Transaction` usan `SoftDeletes`; `Bucket`, `Allocation` y `RecurringTransaction` no. Usa la aserción correcta:

```php
$this->assertSoftDeleted('accounts', ['id' => $account->id]);   // modelos con soft delete
$this->assertDatabaseMissing('budgets', ['id' => $budget->id]); // modelos con borrado definitivo
```

`assertDatabaseMissing` sobre una fila con soft delete falla (la fila sigue físicamente presente con `deleted_at` establecido): este es el falso fallo más común al escribir una prueba de borrado nueva; revisa los traits del modelo antes de asumir qué aserción aplica.

## Factories y claves foráneas entre modelos

Cada modelo de dominio tiene una factory en `database/factories/`. El único borde filoso: **`Account` (como la mayoría de los modelos) está a su vez limitado por el scope `BelongsToUser`**, así que el `account_id => Account::factory()` por defecto de la factory de `RecurringTransaction`/`Transaction`/`Bucket` crea una cuenta propiedad de un usuario *nuevo y sin relación* a menos que la vincules explícitamente al mismo usuario:

```php
// Mal: $account pertenece a algún otro usuario aleatorio creado por la factory.
$recurring = RecurringTransaction::factory()->for($user)->create();

// Bien: account_id y user_id coinciden.
$account = Account::factory()->for($user)->create();
$category = Category::factory()->expense()->create(['user_id' => $user->id]);
$recurring = RecurringTransaction::factory()->for($user)->for($account)->for($category)->create();
```

Hacerlo mal no falla al crear con la factory, sino después y de forma poco obvia, cuando un controlador carga la relación con eager loading (`->with('account')`) y el scope `BelongsToUser` devuelve silenciosamente `null` para una fila que "existe" pero no es visible para el usuario que actúa, produciendo un 500 ("Attempt to read property on null") en lugar de un error de validación. Este error exacto se detectó al escribir `RecurringTransactionsTest` y es también la razón por la que la validación de los Form Request de `account_id`/`category_id` se limita al usuario actual (ver `domain-model.md`): la misma clase de error es alcanzable desde la entrada real de un usuario, no solo desde una configuración de prueba descuidada.

`CategoryFactory::create()` usa por defecto `user_id: null` (una categoría de sistema, visible para todos): pasa `['user_id' => $user->id]` explícitamente siempre que una prueba necesite una categoría que *no* sea visible para otro usuario, o la mitad de "categoría privada de otro usuario" de una prueba entre tenants pasará en silencio por la razón equivocada (la categoría es de sistema, en realidad no pertenece a nadie).

## Props diferidas

`netWorth`/`notifications` se comparten mediante `Inertia::defer(..., 'topbar')` (ver `architecture.md`): **no** están presentes en las `props` de una respuesta de página normal, solo aparecen listadas bajo `deferredProps.topbar` como una promesa que el cliente obtiene después. Para verificar sus valores realmente resueltos, simula directamente el protocolo de recarga parcial de Inertia:

```php
$probe = $this->actingAs($user)->getJson('/dashboard', ['X-Inertia' => 'true']);
$version = $probe->headers->get('X-Inertia-Version'); // no se puede conocer antes de una petición real

$partial = $this->actingAs($user)->getJson('/dashboard', [
    'X-Inertia' => 'true',
    'X-Inertia-Version' => $version,
    'X-Inertia-Partial-Data' => 'netWorth,notifications',
    'X-Inertia-Partial-Component' => 'Dashboard',
]);

$partial->assertJsonStructure(['props' => ['netWorth', 'notifications' => ['items', 'unreadCount']]]);
```

No intentes precalcular `X-Inertia-Version`: no es estable cuando se resuelve fuera del despacho real de una ruta (p. ej., llamar a `Inertia::getVersion()` directamente desde el cuerpo de una prueba, antes de hacer una petición, devuelve un valor distinto al que calcula el middleware para la petición real). Obtenlo del encabezado de una respuesta real, como arriba. Enviar una versión *incorrecta* en una petición GET con el encabezado `X-Inertia` produce un 409, no un 200 con un valor de respaldo: esta es la forma más común en que este patrón de prueba sale mal de manera sutil.

## Qué está cubierto y qué no

Cada módulo de dominio de la migración a Inertia tiene cobertura de pruebas de feature (`tests/Feature/{Accounts,Categories,Budgets,SavingsGoals,RecurringTransactions,Transactions,TransactionImport,Reports,Preferences,Dashboard,Notifications,TransactionImportTemplate}Test.php`): renderizado del index, caminos felices de crear/actualizar/borrar, fallos de validación y autorización entre tenants. Los flujos de autenticación (`tests/Feature/Auth/*`) y la gestión del perfil (`ProfileTest.php`) son las propias pruebas PHPUnit generadas por Breeze, adaptadas al scaffold de Inertia+Vue en lugar de reescritas desde cero.

No cubierto: el *contenido* CSV/PDF de las descargas de reportes más allá del código de estado + `Content-Type` (el renderizado real de Dompdf/CSV se ejecuta, pero no se verifica byte por byte), y las pruebas de interacción del lado de Vue (no hay un runner de pruebas JS a nivel de componente: el flujo de abrir/editar/enviar de `GlobalTransactionModal` se verifica solo a través de los endpoints del backend que llama).

## Prueba de arquitectura

`tests/Architecture/ModuleBoundariesTest.php` es una prueba PHPUnit simple (sin arrancar la app) que escanea `app/Modules/**` en busca de referencias a otros módulos y falla ante violaciones de límites (ver "Modules" en `architecture.md`). Un fallo imprime cada archivo infractor con las clases que no debería importar: reemplaza el import por los `Contracts` del módulo propietario, o mueve el código. También verifica que las antiguas carpetas por tipo (`app/Models`, `app/Services`, ...) no vuelvan a aparecer. `tests/Feature/ModuleContractsTest.php` comprueba que los contratos se resuelvan a sus implementaciones y que cada modelo movido siga encontrando su policy.

## Lógica pura de planificación

`Waterfall`, `ForecastEngine` y `ScenarioSuggester` (bajo `app/Modules/Planning/Support`) no tienen dependencias de framework ni de base de datos, así que se prueban con `TestCase`s simples de PHPUnit en `tests/Unit` usando DTOs armados a mano. Mantén allí las nuevas reglas de pronóstico, con una prueba unitaria fallida primero; reserva las pruebas de feature para el cableado (`PlannerTest`, `IncomeAllocationTest`). En una clase de prueba PHPUnit nunca nombres un helper `run()`: `TestCase::run()` es final.
