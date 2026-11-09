# Manual de usuario de Jura

Jura te ayuda a ver dónde está tu dinero, a dónde se fue y qué puedes planear. Este manual está escrito
para el uso diario. Las secciones marcadas como **Avanzado** son extras opcionales.

> Regla de mantenimiento: una funcionalidad no está terminada hasta que se actualice su sección aquí. Describe lo que la persona
> ve y puede hacer, no cómo funciona el código (eso vive en `architecture.md` y `domain-model.md`).

## Contenido
1. [Primeros pasos](#primeros-pasos)
2. [Panel](#panel)
3. [Cuentas (Bancos y billeteras)](#cuentas-bancos-y-billeteras)
4. [Transacciones](#transacciones)
5. [Importar desde CSV](#importar-desde-csv)
6. [Categorías](#categorías)
7. [Próximos y flujo de caja (ingresos y gastos planeados)](#próximos-y-flujo-de-caja-ingresos-y-gastos-planeados)
8. [Baldes (presupuestos y ahorros)](#baldes-presupuestos-y-ahorros)
9. [Proyectos](#proyectos)
10. [Lista de deseos y proyectos](#lista-de-deseos-y-proyectos)
11. [Planificador](#planificador)
12. [Reportes](#reportes)
13. [Notificaciones](#notificaciones)
14. [Preferencias, perfil y apariencia](#preferencias-perfil-y-apariencia)
15. [Preguntas frecuentes](#preguntas-frecuentes)
16. [Límites de esta versión](#límites-de-esta-versión)

---

## Primeros pasos

Los visitantes que no han iniciado sesión ven una página de bienvenida en la raíz del sitio con los botones **Iniciar sesión** y **Empieza gratis**; los usuarios con sesión iniciada van directo al panel.

1. **Regístrate** y verifica tu correo electrónico. La mayoría de las páginas requieren una cuenta verificada.
2. **Crea tu primera cuenta** (una cuenta bancaria, una billetera, efectivo, etc.) con su saldo actual.
3. **Registra tu primera transacción.** El panel te pide crear una cuenta cuando no tienes ninguna. Cuando ya
   tienes una, muestra tus saldos, con un aviso y un botón **Nueva transacción** hasta que registres la primera.
4. Opcionalmente, define tu **moneda, idioma y zona horaria** en Configuración → Preferencias.

Tus datos son privados. Nadie más puede ver ni abrir tus cuentas, transacciones o presupuestos.

## Panel

Es la primera página después de iniciar sesión. Responde "¿cómo voy este mes?" de un vistazo:

- **Mapa del dinero**: para cada moneda, cuánto hay **en cuentas** (las que están en el presupuesto), **en baldes** y
  **disponible para asignar** (el resto). Consulta *Baldes*.
- **Patrimonio neto total**: la suma de los saldos de tus cuentas.
- **Ingresos de este mes**, **Gastos de este mes** y **Ahorro de este mes**.
- **Gasto diario promedio**, **Mayor gasto del mes** y **Categoría con más gasto**.
- **Días de autonomía financiera**: cuántos días te alcanzaría tu dinero disponible a tu ritmo de gasto actual
  si no llegara ningún ingreso nuevo.
- Una tarjeta **Por revisar** cuando hay planes esperándote: dinero planeado cuya fecha ya pasó y que sigue abierto,
  o que no se pudo registrar automáticamente. Muestra los cinco primeros con **Confirmar**, **Vincular**, **Ajustar**,
  **Omitir** y **Editar**, y **Ver todos** abre la lista completa. El mismo número aparece junto a **Próximos y flujo de
  caja** en el menú.
- Una tarjeta de **Próximos 30 días** con los ingresos planeados, los gastos planeados y el neto, claramente marcados como planeados y aún no reales
  (aparece solo cuando tienes eventos planeados; el ingreso cuenta cuando está confirmado o esperado).
- Breves **insights** en lenguaje sencillo, por ejemplo "Gastaste 12% más que el mes pasado".
- Gráficos: **Gastos por categoría** y la **Evolución mensual**.

Los montos en distintas monedas nunca se suman; cada moneda se muestra por separado.

## Cuentas (Bancos y billeteras)

Menú: **Dinero → Bancos y billeteras**.

Una cuenta es cualquier lugar que guarda dinero: una cuenta bancaria, una billetera digital, efectivo, una tarjeta de crédito o una cuenta de ahorros.

- **Agrega una cuenta** con un nombre, tipo, saldo inicial y moneda. También puedes indicar un banco o institución, color,
  ícono, número parcialmente oculto y notas.
- **Intereses (rendimiento).** Si la cuenta genera intereses, ingresa la **tasa de interés anual** y cuándo se acredita
  (diaria, mensual o anual). El pronóstico del flujo de caja mostrará entonces una columna proyectada de **Intereses**. Los intereses existen solo
  en cuentas reales, nunca en baldes, y son solo una proyección: no se registra nada hasta que tú agregues los intereses como una
  transacción real. No planees además los mismos intereses como un ingreso planeado, o se contarán dos veces.
- **El saldo se calcula por ti** a partir del saldo inicial más cada transacción. Nunca escribes el saldo
  actual directamente. Para corregirlo, agrega una transacción.
- **Contar en mi presupuesto.** Activado por defecto. Desactívalo para el dinero que nunca piensas gastar (un depósito a
  largo plazo, una cuenta de inversión): la cuenta queda marcada como **Fuera del presupuesto** y su saldo no entra en lo
  *disponible para asignar* (consulta *Baldes*). Una cuenta archivada queda fuera del presupuesto de la misma manera.
  Mientras una cuenta está fuera del presupuesto, nada de lo que pagó sale de un balde: los gastos pagados desde un balde
  le devuelven su dinero a ese balde, y su gasto en una categoría ya no reduce el balde de esa categoría. Lo disponible
  para asignar cambia según el saldo de la cuenta y lo que los baldes recuperan; en una tarjeta cuyo gasto salió todo de
  baldes, ambas cosas se compensan y no cambia en absoluto. Volver a activarlo, o desarchivar la cuenta, saca de nuevo
  ese gasto de los baldes. Los ingresos de la cuenta que ya llenaron baldes se quedan en ellos.
- **Eliminar una cuenta:** si no tiene transacciones, se elimina. Si tiene transacciones (incluidas las que eliminaste y
  siguen en la Papelera) o la usa un plan repetido activo, se **archiva**: se conserva en tu historial para que tus reportes sigan siendo correctos, pero
  ya no puede recibir nuevas transacciones, transferencias, planes registrados automáticamente ni compras. Las transacciones
  que ya tiene se pueden seguir editando.

## Transacciones

Menú: **Dinero → Transacciones**. Las pestañas bajo el título de la página (**Transacciones**, **Ingresos**, **Gastos**, **Transferencias**) filtran la lista por tipo, y el encabezado de la página tiene **Nueva transacción** e **Importar CSV**.

Hay tres tipos:

| Tipo | Úsalo cuando | Efecto |
|---|---|---|
| **Ingreso** | recibes dinero | aumenta el saldo de la cuenta |
| **Gasto** | gastas dinero | disminuye el saldo de la cuenta |
| **Transferencia** | mueves dinero entre tus propias cuentas | disminuye una cuenta y aumenta la otra; no cuenta como ingreso ni como gasto |

Para agregar una, usa el botón de agregar (está disponible desde cualquier página). El mismo formulario registra dinero que
**Ya ocurrió** (una transacción real, la opción por defecto) o dinero **Planeado** (no toca tu saldo; consulta
[Próximos y flujo de caja](#próximos-y-flujo-de-caja-ingresos-y-gastos-planeados)). Para una real, elige la cuenta, categoría, monto, fecha y
una **hora** opcional (por defecto es la actual y se puede borrar), una descripción opcional, notas y método de pago (efectivo, tarjeta débito, tarjeta de crédito, transferencia bancaria u otro).
Si eliges una fecha posterior a hoy, el formulario lo señala y ofrece **Planearlo en su lugar** o **Ya ocurrió,
guardarlo**; solo se guarda cuando eliges la segunda (también cuando al editar una transacción la mueves después de hoy).
Las importaciones CSV no preguntan. Para un plan, el formulario pregunta qué tan seguro estás, si **se repite**, si
**registrarlo automáticamente en la fecha** y, para un ingreso, a dónde va. Un plan único con fecha anterior a hoy
sugiere registrarlo como algo que ya ocurrió. También se puede planear una transferencia (de una cuenta a otra de la misma moneda).

**Pagar desde un balde (opcional, solo gastos).** Elige el balde que paga un gasto, por ejemplo *Viaje* para un
hotel. El monto completo sale de ese balde, aunque lo deje por debajo de cero (la tarjeta dice entonces
**Sobregastado**), y lo disponible para asignar no cambia. Solo se ofrecen los baldes en la moneda de la cuenta que no
siguen una categoría; el gasto de una categoría ya sale de su balde de gasto, y el formulario te avisa cuando la
categoría que elegiste se cuenta en uno (un gasto pagado desde un balde no se cuenta también ahí). Editar o eliminar el
gasto, o restaurarlo desde la Papelera, actualiza también el balde. Las cuentas que no cuentan en tu presupuesto no
pueden pagar desde un balde, y mover un gasto a una de ellas olvida su balde. Eliminar el balde conserva el gasto.
Un balde completado no acepta gastos nuevos, pero un gasto ya pagado desde él lo conserva cuando editas el gasto (el
formulario lo muestra como *actual*).

**Planes encima de la lista.** La primera página de la lista (sin rango de fechas) muestra tus **planes vencidos** (en
rojo) y lo que viene en los **próximos 30 días** (punteado: proyectado, no está en tu saldo), siguiendo la cuenta, la
categoría y el tipo por los que filtraste. Se muestran hasta 10 de cada uno, con **Ver los N** para el resto. Cada uno
tiene **Confirmar** (lo registra tal como está planeado con un clic cuando tiene cuenta y categoría), **Ajustar**
(confirmar con otro monto, fecha o cuenta), **Omitir**, **Editar**, y **Vincular** cuando se parece a una transacción
similar que ya registraste. Las transacciones registradas automáticamente muestran **Registrado automáticamente**, y las
confirmadas desde un plan muestran **Desde un plan** (abre ese plan en la página de flujo de caja).

- Puedes **editar** o **eliminar** cualquier transacción; los saldos se actualizan automáticamente. Una transacción
  conserva su cuenta y su categoría cuando la editas, aunque desde entonces esa cuenta se haya archivado o la categoría se haya eliminado.
- **Filtra** la lista por tipo para ver solo ingresos, gastos o transferencias.
- **Una moneda por transacción.** Una transferencia debe ser entre dos cuentas de la misma moneda, y una transacción
  solo se puede mover a otra cuenta de su moneda (el *Cambiar cuenta* masivo omite las que no coinciden). La moneda de
  una cuenta ya no se puede cambiar una vez que tiene transacciones o la usa un plan, un plan repetido, un balde o un
  artículo de la lista de deseos, ni la de un balde una vez que tiene dinero.
- Editar o eliminar un lado de una transferencia actualiza también el otro lado. Cada transferencia aparece dos veces en la lista completa:
  la fila dice **Transferencia saliente → (destino)** en la cuenta de origen y **Transferencia entrante ← (origen)** en la cuenta de destino.
- Dentro de un día, las transacciones se listan de la hora más reciente a la más antigua; las que no tienen hora van al final. La hora se muestra en el formato
  que elegiste en Preferencias (12 o 24 horas).

## Seleccionar varias filas a la vez

Las listas largas (transacciones, eventos planeados, lista de deseos, baldes) tienen una casilla en
cada fila y una casilla **Seleccionar todo**. Al seleccionar filas se abre una barra con **Eliminar** y **Limpiar**.

- En **Transacciones** la barra también ofrece **Cambiar categoría / cuenta** para las filas seleccionadas (las transferencias nunca se
  modifican, una categoría solo aplica a transacciones del mismo tipo, y una cuenta solo a transacciones de su moneda) y, una vez que marcas toda la página, **Seleccionar
  los N que coinciden** para actuar sobre todas las filas que cumplen tus filtros, no solo las de esta página.
- Después de eliminar transacciones, un aviso ofrece **Deshacer**; las transacciones eliminadas también esperan en **Configuración → Papelera**.
- Eliminar eventos planeados quita solo las ocurrencias elegidas de un evento repetitivo. Eliminar baldes deja su dinero
  disponible para asignar de nuevo.

## Importar desde CSV

Menú: **Dinero → Importar CSV** (también es un botón en la página de Transacciones).

Úsalo para traer historial desde una hoja de cálculo o una exportación de tu banco.

1. Descarga la **plantilla** desde la página de importación si quieres un archivo ya preparado.
2. Sube tu CSV. Jura lo lee y muestra una **vista previa** con los problemas señalados antes de guardar nada.
3. Confirma para importar las filas válidas.

Reglas:
- Columnas obligatorias: **fecha, cuenta, categoría, tipo, monto**. Opcionales: descripción, notas, **hora** (como `14:30` o `2:05 PM`).
- Los nombres de las columnas pueden estar en inglés o en español; también se entienden alternativas comunes, como "valor" para el monto.
- Hasta **1000 filas** por archivo.
- Los nombres de cuentas y categorías deben coincidir con los que ya tienes; las cuentas archivadas no se emparejan.
- Las **fechas** son `2026-08-01` o `01/08/2026` (día/mes/año). Las fechas que no existen, como 31/02, se señalan
  en lugar de pasar al mes siguiente.
- Los **montos** son positivos, con dos decimales como máximo, escritos con cualquier separador (`1234.56`, `1.234,56`,
  `1,234.56`). Cuando un único separador va seguido de tres dígitos (`1,234` o `1.234`), Jura lo lee usando el
  separador decimal elegido en **Configuración → Preferencias** (o el de tu idioma). Los montos como `1e3` se rechazan.

## Categorías

Menú: **Configuración → Categorías**.

Las categorías agrupan tus ingresos y gastos (por ejemplo Alimentación, Salario). Jura incluye un conjunto de **categorías
del sistema** compartidas por todos, y puedes agregar las tuyas.

- Una categoría es de **Ingreso** o de **Gasto**.
- Las categorías del sistema, y las que ya tienen transacciones, **no se pueden editar ni eliminar**, para que tu
  historial nunca cambie de significado. Crea una categoría nueva en su lugar.
- Una categoría que usa un balde, un evento planeado abierto o un plan repetido tampoco se puede eliminar
  (ni quitar de la Papelera); cambia o elimina esos primero.
- Tus propias categorías tienen una casilla, además de botones para editar y eliminar. Marca varias (o **Seleccionar todo**) para eliminarlas
  juntas; las que ya tienen transacciones o las usan planes se conservan, y las eliminadas van a la **Papelera**.

## Próximos y flujo de caja (ingresos y gastos planeados)

Menú: **Plan → Próximos y flujo de caja**.

Usa esta página para anotar el dinero que esperas recibir o gastar en el futuro, por irregular o regular que sea
(arriendo, salario, suscripciones), y ver cómo podría verse tu saldo. Los eventos planeados son **estimaciones**: nunca cambian tus saldos reales ni tus reportes
hasta que los marques como hechos.

**Agregar un evento planeado.** Presiona **Agregar** (o el botón de agregar en cualquier página y elige **Planeado**), y
luego completa qué es, el monto, la fecha y qué tan seguro estás. Puedes elegir una cuenta y una categoría, agregar notas,
hacer que se **repita** (semanal, cada dos semanas, mensual o anual), opcionalmente hasta una fecha, y marcar **Registrarlo
automáticamente en la fecha** (ver más abajo). Los eventos repetitivos se crean desde hoy y hasta 12 meses adelante y se
extienden automáticamente; las proyecciones y el planificador que miran más lejos (hasta 36 meses) igual cuentan las
fechas posteriores. Si la primera fecha está en el pasado, solo define el ritmo: las ocurrencias anteriores a hoy no se
crean (regístralas como transacciones reales si ocurrieron).

**Registrar automáticamente.** Un plan con **Registrarlo automáticamente en la fecha** (necesita una cuenta y una
categoría) se convierte en una transacción real en su fecha, en tu zona horaria, sin que hagas nada. Se marca como
**Auto** en la página. Si se pasaron algunas fechas (por ejemplo, la aplicación no estaba funcionando), cada una se
registra en su propia fecha la próxima vez que funcione. Si su cuenta se archivó o se eliminó, o su categoría se eliminó,
el plan sigue abierto con una nota en rojo que explica por qué; corrige la cuenta o la categoría y se registrará en el
siguiente intento. Editar el plan borra la nota hasta el siguiente intento.

**Por revisar.** En la parte superior de la página te esperan los planes cuya fecha ya pasó y siguen abiertos, y los
planes que no se pudieron registrar automáticamente: **Confirmar** los registra (con un clic cuando el plan tiene cuenta y
categoría, o ambas cuentas en una transferencia; si no se puede registrar, se abre el formulario para mostrar por qué), **Vincular** enlaza una transacción que ya registraste, **Ajustar** confirma con otros datos, **Omitir** indica que
no ocurrió. Un plan vencido cuenta como si ocurriera hoy en todas las proyecciones hasta que te ocupes de él.

**Transferencias planeadas.** Elige **Transferencia** para planear mover dinero entre dos de tus cuentas de la misma moneda
(por ejemplo, un traslado mensual a ahorros). Necesita ambas cuentas y ninguna categoría, puede repetirse y registrarse
sola como cualquier plan, y confirmarla registra ambos lados de la transferencia. No cambia los ingresos ni los gastos
proyectados (el dinero sigue siendo tuyo), pero mueve el saldo proyectado entre las dos cuentas, así que los intereses se
proyectan en la cuenta correcta. Por ahora la proyección no muestra cómo una transferencia planeada hacia o desde una
cuenta fuera del presupuesto cambia lo disponible para asignar.

**¿Qué tan seguro estás?**
- **Confirmado**: va a suceder (un contrato firmado, una factura fija).
- **Esperado**: probable, pero no seguro.
- **Especulativo**: una esperanza o un tal vez.

**Tres vistas del futuro** (el selector en la parte superior de la página):
- **Conservador**: solo cuenta el ingreso confirmado.
- **Esperado**: cuenta el ingreso confirmado y el esperado.
- **Optimista**: cuenta todo el ingreso planeado.

*Esperado* es el valor por defecto en todas las páginas que proyectan el futuro (flujo de caja, planificador, baldes, reportes).

Los gastos planeados siempre cuentan completos en todas las vistas. El gráfico y la tabla muestran, mes a mes, los ingresos
planeados, los gastos planeados y tu saldo proyectado, por moneda. El saldo de hoy es tu patrimonio neto real.

Dos columnas más miran solo tu presupuesto:
- **Efectivo en el presupuesto**: el saldo proyectado de las cuentas de tu presupuesto (se dejan fuera las cuentas fuera
  del presupuesto y los intereses proyectados; las transferencias planeadas hacia o desde una cuenta fuera del presupuesto
  meten o sacan dinero de él).
- **Disponible para asignar (proyectado)**: ese efectivo menos lo que tus baldes tendrían al final del mes (incluidos sus
  ingresos planeados, el gasto planeado en su categoría y sus movimientos de fin de ciclo). Un número negativo significa
  que tus baldes tendrían más que tus cuentas: planea menos para los baldes o más ingresos.

**Cuando el dinero realmente se mueve.**
- Presiona el botón de **check** (*Marcar como hecho*) para registrar la transacción real a partir del plan. Cuando el
  plan tiene una cuenta y una categoría (ambas cuentas en una transferencia), un clic lo registra en la fecha planeada
  (hoy si el plan tiene una fecha posterior); si no, o cuando no se puede registrar (una cuenta archivada, por ejemplo),
  se abre el formulario, ya completado, para pedir lo que falta. Edita la transacción después para cambiar cualquier cosa.
- Si ya registraste tú mismo una transacción similar, el plan muestra **Parece hecho: ...** con un botón **Sí, vincularlo**.
  Nunca se vincula nada sin tu confirmación.
- Presiona **Omitir** para algo que no va a suceder. Un plan omitido permanece en la lista (atenuado) con **Reabrir**
  para volver a planearlo y **Eliminar** para quitarlo.
- **Eliminar** quita un plan definitivamente. Eliminar una ocurrencia de un evento repetitivo nunca la trae de vuelta. Eliminar varios
  a la vez no toca los planes ya marcados como hechos.
- Un plan marcado como hecho no se puede reabrir directamente. Para deshacerlo, elimina su transacción real: el plan vuelve
  a quedar abierto. Restaurar esa transacción desde la Papelera marca el plan como hecho de nuevo, salvo que ya lo hayas
  cerrado con otra transacción. Un plan que se registró solo no se vuelve a registrar después de que eliminas su
  transacción, tampoco cuando la eliminas definitivamente de la Papelera: una vez que pasa su fecha, espera en **Por
  revisar** a que lo confirmes, lo vincules o lo omitas.

**Cambiar eventos repetitivos.** Al editar o eliminar una ocurrencia, se te pregunta si es **solo este** o **este
y todos los siguientes**. Las ocurrencias anteriores, y las ya hechas, nunca se tocan. Cuando cambias "todos los siguientes",
las fechas se mantienen como están; para mover una fecha, edita esa ocurrencia individual. Mover una ocurrencia nunca la
trae de vuelta en su fecha anterior y nunca omite las fechas que la rodean.

**Planes repetidos.** La lista **Planes repetidos** muestra cada regla con su próxima fecha, frecuencia, fecha de fin y
si se registra automáticamente (**Auto**) o está **En pausa**.
- **Editar** cambia todo el plan desde hoy: las fechas anteriores a hoy que siguen abiertas (vencidas) conservan lo que
  tenían. Cambiar la frecuencia o la **Próxima fecha** mueve todas las fechas siguientes al nuevo ritmo; una fecha de fin
  anterior (o eliminar una fecha "y todas las siguientes") quita las fechas abiertas posteriores, y si vuelves a quitar la
  fecha de fin, las fechas posteriores que ya habías confirmado u omitido no se vuelven a planear. Una fecha que ya confirmaste por adelantado u omitiste cuenta para la nueva fecha más cercana a la
  fecha para la que estaba planeada (aunque la hubieras movido), cuando esa nueva fecha está a menos de medio periodo de
  ella (del ritmo más corto cuando cambias la frecuencia: 3 días para semanal, 7 para cada dos semanas, 15 para mensual, medio año para anual; la posterior cuando dos están igual de cerca), para que
  ese pago no se planee dos veces. Todas las demás fechas nuevas se conservan, y también la **Próxima fecha** que
  escribas, salvo que ya hayas confirmado u omitido la fecha planeada exactamente para ese día.
- **Pausar** quita sus próximas fechas; las vencidas se quedan como recordatorios y ya no se registran automáticamente.
  **Reanudar** empieza de nuevo desde hoy y nunca registra las fechas que se pasaron mientras estaba en pausa; las fechas
  que ya confirmaste u omitiste (o eliminaste), también las que habías movido a otro día, no se vuelven a planear. Pausar
  y reanudar otra vez no cambia nada más.
- **Eliminar** quita el plan y sus fechas abiertas; las fechas ya registradas u omitidas siguen en tu historial.

**Intereses.** Las cuentas con tasa de interés agregan una línea de intereses proyectados a cada mes (los ingresos y
gastos planeados en esa cuenta mueven su saldo en sus fechas, así que los intereses los siguen).

**Avanzado.** Un plan vencido que sigue abierto cuenta en el mes actual. Las distintas monedas se proyectan
por separado y nunca se suman.

## Baldes (presupuestos y ahorros)

Menú: **Plan → Baldes**. (La antigua página de **Presupuestos** ahora vive aquí; su dirección redirige.)

Un **balde** es dinero que apartas para algo. Los baldes solo *reservan* dinero: tus cuentas y saldos reales
nunca cambian. Hay dos tipos:

- **Límite de gasto**: elige una categoría y un límite por mes o año. La tarjeta muestra cuánto has gastado, cuánto
  queda, y te avisa al llegar al 80% y cuando te pasas. Esto es lo que antes eran los presupuestos.
- **Ahorro**: un fondo para una meta (un carro, un viaje, un fondo de emergencia), con una meta opcional. La tarjeta muestra lo que hay
  disponible y qué tan cerca estás de la meta. Dale una **fecha objetivo** (necesita un monto objetivo) y la tarjeta
  también dice si vas **Al día** o **Atrasado**, y cuánto apartar cada mes para lograrlo. Si tu plan de ingresos llena
  el balde (tiene una regla de llenado, o un ingreso planeado le pone dinero), la tarjeta dice el día en que tu plan lo
  llena, y *Atrasado* significa que el plan no llega antes de la fecha objetivo. Para cualquier otro balde, Jura compara
  el dinero apartado hasta ahora con un ritmo parejo desde el día en que creaste el balde hasta la fecha objetivo (con
  dos puntos porcentuales de margen).

**Disponible para asignar.** El mapa del dinero en la parte superior de esta página (también en el Panel y en Cuentas, y
como **Disponible para asignar** en la barra superior) muestra, por moneda, el dinero **en cuentas** (solo las cuentas que
cuentan en tu presupuesto), el dinero **en baldes** (todos los baldes que alguna vez recibieron dinero; un límite de gasto
que nunca recibió dinero es solo un límite y no tiene nada) y lo que está **disponible para asignar**: en cuentas menos en
baldes. Siempre se calcula, nunca se guarda. Cuando es negativo, muestra **Asignado de más por** ese monto, en rojo: tus
baldes prometen más dinero del que tienes, así que saca algo de un balde.

**Llenar baldes con el ingreso.** Abre un balde y, en *Llenado y opciones avanzadas*, elige cómo se llena cuando
llega un ingreso:
- **Porcentaje del ingreso**: por ejemplo el 20% de cada ingreso.
- **Monto fijo**: por ejemplo 150.000 de cada ingreso. Si el balde tiene un ciclo, el monto es por ciclo: el
  ingreso completa el balde hasta ese valor, así que tres pagos en un mes siguen dando 150.000 en total, no 450.000.
- **Parte de lo que queda**: un porcentaje del ingreso que todavía no ha sido reclamado cuando se llega a este balde en el
  orden de prioridad. Ponlo después de los baldes de monto fijo: por ejemplo Fondo para el portátil 75% de lo que queda y
  Extras 100% de lo que queda; lo que el Fondo para el portátil no pueda recibir (porque está lleno) pasa a Extras.
- **Llenar hasta su meta**: completa lo que falte.

- **Súbelo después** (opciones avanzadas): desde el ciclo *siguiente* a que otro balde alcance su meta (o se complete),
  un llenado fijo o por porcentaje cambia a un nuevo valor. Ejemplo: Fondo de emergencia 100.000 por mes, y luego 250.000
  una vez que el Fondo para el portátil esté lleno. Entra en vigor desde el ciclo siguiente, nunca a mitad de ciclo.

A cada ingreso, los baldes se ofrecen en **orden de prioridad** (primero P0, luego P1, y así sucesivamente), de modo que los importantes se llenen antes que
el resto. Un balde con meta deja de llenarse una vez alcanzada la meta. Lo que ningún balde reclama queda **disponible para
asignar**. El llenado ocurre solo, pero **únicamente cuando al menos un balde tiene una regla de llenado**; sin reglas, no
se asigna nada y todo el ingreso queda disponible para asignar. El ingreso en una cuenta que no cuenta en tu presupuesto
nunca llena un balde. Si cambias el monto, la fecha o la cuenta de un ingreso,
sus llenados se rehacen; cambiar solo su descripción, notas, categoría o método de pago no toca los baldes.
Eliminar un ingreso quita sus llenados. Un balde solo devuelve lo que todavía tiene: si parte de ese ingreso ya se movió a
otro balde, se gastó o se trasladó, el balde se detiene en cero y la diferencia sale en cambio de lo disponible para
asignar, que entonces puede quedar en negativo (**asignado de más**: dinero que habías reservado pero ya no tienes).
Restaurar el ingreso desde la Papelera, o cambiar solo su fecha, deja todo exactamente como estaba.

**Decidir para un ingreso concreto.** En **Próximos y flujo de caja**, cuando planeas un ingreso, *¿A dónde va este ingreso?* te permite escribir exactamente cuánto
va a cada balde solo para ese ingreso. El resto queda disponible para asignar. Un balde que solo se llena desde otras
cuentas, o que está completado, se omite. El mismo reparto se usa cuando vinculas
el plan a un ingreso que ya registraste, y de nuevo cada vez que los llenados de ese ingreso se rehacen (una edición, un
cambio masivo o una restauración desde la Papelera).

**Mover dinero.**
- **Mover dinero** (el ícono de flechas) transfiere fondos de un balde a otro de la misma moneda, o de vuelta a
  **Disponible para asignar**. Elige *Meter en este balde, desde disponible para asignar* para poner dinero sin asignar en el balde.
- **Asignar desde disponible para asignar** (en el formulario del balde) es el dinero con el que empieza un balde, tomado
  de lo disponible para asignar. Cuenta desde el día en que lo defines por primera vez, así que en un balde de gasto solo
  el gasto de la categoría desde ese día sale de él: asignar 400 toma exactamente 400 de lo disponible para asignar,
  aunque ya hayas gastado en esa categoría antes en este mes. Cámbialo o bórralo en cualquier momento; solo afecta a los
  baldes, nunca a tus cuentas reales.
- **Cuándo un balde de gasto empieza a contar su categoría.** Desde el primer día en que entró dinero en él. Quitar ese
  primer movimiento después no cambia ese día, así que el gasto ya contado sigue contado. Si quitas todos los movimientos
  de un balde cuya categoría no ha tenido gasto desde entonces, vuelve a ser solo un límite.
- **Más de lo disponible.** Poner en un balde más dinero del que está disponible para asignar (con el monto inicial, una
  adición o un traslado desde disponible para asignar) te pide confirmar primero, porque tus baldes tendrían entonces más
  que tus cuentas. Si confirmas, el mapa del dinero muestra la diferencia como **asignado de más** hasta que lo corrijas.
- **Agregar o quitar fondos** (el ícono de billetes) corrige un saldo a mano. Usa un monto negativo para quitar, hasta
  el monto disponible (en un balde de gasto, lo que queda después del gasto de este ciclo).
- **Toda la actividad, editar o quitar movimientos** (en *Actividad reciente*) lista todo lo que alguna vez entró o salió del
  balde. Los movimientos que hiciste tú mismo (saldo inicial, ajustes, traslados entre baldes) tienen un botón de papelera, y
  **Poner en cero** deja en cero lo que tiene el balde: un balde con dinero lo devuelve a disponible para asignar, y uno
  sobregastado se completa desde disponible para asignar. Quitar un movimiento pide confirmación primero: se elimina para siempre, y
  quitar un traslado entre baldes también quita el movimiento correspondiente del otro balde. Los movimientos creados por
  un ingreso o por el cierre de un ciclo no se pueden quitar. Cuando poner en cero o quitar un movimiento pondría en el
  balde más dinero del que está disponible para asignar (por ejemplo, quitar un traslado de vuelta a disponible para
  asignar), se te pide confirmar, como con cualquier asignación.
- Eliminar un balde deja su dinero disponible para asignar de nuevo (o, si estaba sobregastado, reduce lo disponible para asignar).

**Ciclos.** Un balde de gasto funciona en ciclos: define **Ciclo: se repite cada N días / semanas / meses / años**, y
opcionalmente una **fecha de inicio** (por ejemplo el 15, para seguir tu día de pago; vacío significa meses calendario). Un mes
corto nunca lo rompe: un ciclo que empieza el 31 empieza el 28 en febrero.

**Qué pasa durante un ciclo.** Digamos que ganas 1000 al mes, y **Mercado** tiene un límite de 500 en **P0**, se llena hasta
su meta y envía su sobrante a **Emergencia** (meta 1000, **P10**):
1. Cuando empieza el mes, la tarjeta del balde muestra lo que se **espera** (en cursiva): 500 para el mercado y 500 para el
   fondo de emergencia, tomados del ingreso que planeaste en Próximos y flujo de caja. Es solo una estimación, aún no se guarda nada. Cuando
   llega el ingreso real, llena los baldes de verdad y la estimación desaparece.
2. Durante el mes gastas, digamos, 100 en el mercado. La tarjeta muestra que unos 400 pasarán al siguiente al final.
3. Cuando termina el ciclo, los 400 que no se gastaron pasan al fondo de emergencia, que empieza el mes siguiente con 900.
   El ingreso del mes siguiente vuelve a llenar el mercado hasta 500 y solo agrega los 100 que le faltan al fondo de emergencia.

**Historial de ciclos.** **Historial de ciclos** en la tarjeta de un balde lista cada ciclo terminado (con qué empezó, qué lo llenó,
qué entró o salió, qué se gastó, qué se desbordó y qué sobró) con un gráfico. Cada ciclo se guarda cuando
se cierra, así que el historial sigue siendo correcto aunque cambies el balde después. Los ciclos que terminaron mientras Jura no estaba funcionando
se cierran en orden la próxima vez que funcione.

**Ciclos en baldes de ahorro.** Un balde de ahorro también puede tener un ciclo (elige uno en el formulario; *Sin ciclo* es el
valor por defecto). Guarda el mismo historial que un balde de gasto, y puedes definir una **meta de ahorro por ciclo** (por ejemplo
200 al mes). La tarjeta muestra lo que ha recibido en lo que va del ciclo frente a esa meta, y la página de historial muestra la meta
de cada ciclo terminado. La meta general del balde no cambia: el dinero que se arrastra cuenta para ella.

**Un límite distinto para un ciclo.** En la página de historial, **Límite solo para este ciclo** cambia el límite del
ciclo actual (por ejemplo un mes de vacaciones). Bórralo para volver al límite de siempre.

**Fin del ciclo (avanzado).** Elige qué pasa con lo que sobra: **dejarlo** en el balde, **devolverlo a disponible
para asignar**, o **moverlo** a otro balde (el balde de excedente). La tarjeta y la página de historial muestran con qué
se espera que termine el ciclo actual: el ingreso planeado que aún falta (en la vista *Esperado*), menos el gasto
planeado en su categoría, y lo que entonces se devolvería o se movería.

**Solo desde ciertas cuentas (avanzado).** Marca cuentas para que un balde se llene solo con el ingreso que llega a ellas, por
ejemplo un fondo de "Dinero físico". El ingreso en otras cuentas lo omite. Solo se listan las cuentas en la moneda del balde.

**Gasto planeado.** Los gastos que planeas en **Próximos y flujo de caja** para la categoría de un balde de gasto aparecen en su tarjeta como
**planeado**, junto con lo que quedaría **después de lo planeado**.

**Completar un balde.** Cuando una meta está cumplida, un balde de ahorro se puede **completar**: lo que queda en él pasa a
su balde de "Mover el sobrante a" (o vuelve a disponible para asignar) y deja de recibir ingresos, de modo que el ingreso sigue hacia los
siguientes baldes. Hay dos maneras:
- **Automáticamente**: en el formulario del balde (*Llenado y opciones avanzadas*) marca *Cerrar este balde cuando se compre un artículo de la lista de deseos
  pagado con él*, y define *Sobrante al final del ciclo* como *Moverlo a otro balde* y elige adónde. Comprar el artículo desde la
  lista de deseos completa entonces el balde. El **Planificador** hace lo mismo en la fecha de compra cuando simula.
- **A mano**: **Marcar como completado** en la tarjeta del balde. **Reabrir** lo pone a trabajar de nuevo.

Deshacer la compra de un artículo de la lista de deseos reabre el balde y recupera su sobrante: completo desde disponible
para asignar, o desde el balde al que se movió, hasta lo que ese balde todavía tenga (lo que ya usó queda usado).

**Ejemplo práctico: ahorrar para un portátil.** Ganas 3.000.000 en el mes 1 (dos pagos) y 2.400.000 al mes después de
eso, y quieres dinero para ocio, un fondo de emergencia, un fondo de 6.000.000 para el portátil y un fondo para extras:

| Balde | Tipo | Configuración |
| --- | --- | --- |
| Ocio | Límite de gasto | Categoría *Entretenimiento*, límite 200.000 al mes, **P0**, llenado: monto fijo 200.000 |
| Fondo de emergencia | Ahorro | Ciclo: cada 1 mes, **P1**, llenado: monto fijo 100.000, *Súbelo después*: 250.000 cuando el *Fondo para el portátil* esté lleno |
| Fondo para el portátil | Ahorro | Meta 6.000.000, **P2**, sin ciclo, llenado: parte de lo que queda 75%, marca *Cerrar este balde cuando se compre un artículo de la lista de deseos pagado con él*, el sobrante pasa a *Extras* |
| Extras | Ahorro | **P3**, sin ciclo, llenado: parte de lo que queda 100% |

Luego agrega un **portátil** a la lista de deseos por 6.000.000 usando el balde *Fondo para el portátil*, y planea tus ingresos en
**Próximos y flujo de caja**. Esto es lo que verás:
- Cada mes, Ocio toma 200.000 y el Fondo de emergencia 100.000 una sola vez, sin importar cuántos pagos lleguen. Lo que
  queda se reparte 75% al Fondo para el portátil y el resto a Extras. Cuando el Fondo para el portátil está lleno, el resto va a Extras.
- En el **Planificador**, *Sugerir el mejor orden* te da el mes en que puedes comprar el portátil. Cuando realmente lo compras desde la
  lista de deseos, el Fondo para el portátil se cierra, lo que supere 6.000.000 pasa a Extras, y el Fondo de emergencia sube a
  250.000 desde el mes siguiente.
- En **Próximos y flujo de caja**, una cuenta con tasa de interés (ver *Cuentas*) agrega una columna proyectada de *Intereses*, así que el
  pronóstico incluye lo que rinden las "cajitas" de tu banco.

## Proyectos

Menú: **Plan → Proyectos**.

Un proyecto es una meta formada por varias cosas que quieres, por ejemplo un estudio en casa o un viaje, con su propio plan de dinero.
Presiona **Nuevo proyecto** y completa:

- un **nombre**, un **tipo** (proyecto, pasatiempo, categoría, otro), un **color** y notas;
- una **moneda**: una por proyecto, y todos los artículos del proyecto deben usarla;
- un **presupuesto** y una **fecha objetivo** opcionales;
- un **balde de ahorro** opcional. Elige **Usar uno de mis baldes de ahorro** (uno que ningún otro proyecto use, en la
  moneda del proyecto) o **Crear un balde para este proyecto**: Jura crea un balde de ahorro con el nombre del proyecto,
  cuya meta es el presupuesto (o lo que cuestan los artículos deseados), y lo llena con tus ingresos como elijas (un
  monto fijo cada mes, un porcentaje de cada ingreso o una parte de lo que queda después de los demás baldes). Los
  artículos del proyecto que no tienen balde propio se pagan con el balde del proyecto.

Cada proyecto es una tarjeta con una barra (lo que ya compraste, lo que cubren tus baldes, lo que todavía se desea) y:

- **Presupuesto**, **Planeado** (lo que cuestan los artículos deseados), **Gastado** (lo que realmente costaron las compras) y **Queda**
  (presupuesto - gastado - planeado; en rojo cuando te pasas);
- **Todo comprado el**: la fecha en que se podría comprar el último artículo deseado, siguiendo el mejor orden del
  planificador para *todos* tus artículos deseados juntos (ingreso Esperado, 24 meses hacia adelante), para que dos
  proyectos nunca cuenten con el mismo dinero libre. *No en 24 meses* significa que el dinero no llega a tiempo. Con una
  fecha objetivo también dice **A tiempo** o **Después de la fecha objetivo**;
- cuánto **apartar cada mes** para cubrir lo que los baldes todavía no cubren, hasta la fecha objetivo.

Abre un proyecto para ver cada artículo (cuándo se podría comprar, cómo se paga, cuánto costó realmente), su balde y
las cifras del presupuesto, y para editarlo o eliminarlo. Eliminar un proyecto conserva sus artículos (sin proyecto) y su
balde, salvo que marques **Eliminar también su balde de ahorro**, en cuyo caso el dinero que tenga vuelve a estar disponible para asignar.
Los artículos que no pertenecen a ningún proyecto aparecen en una última tarjeta, **Sin proyecto**.

## Lista de deseos y proyectos

Menú: **Plan → Lista de deseos**.

Un lugar para todo lo que te gustaría conseguir. Agrupa las cosas en **proyectos** (un estudio en casa, un viaje), **pasatiempos**
(ciclismo) o cualquier otra agrupación, y califica cuánto quieres cada una.

- **Nuevo proyecto** (también en **Plan → Proyectos**, ver arriba) crea un grupo. Eliminar un proyecto conserva sus artículos
  bajo "Sin proyecto". En la página de Proyectos, el botón **Nuevo artículo** de un proyecto vuelve aquí con el formulario
  del artículo abierto y el proyecto ya elegido.
- **Nuevo artículo** necesita un nombre y un precio. Define **cuánto lo quieres** (de 1 a 5) y elige un **proyecto** (o
  presiona *Nuevo proyecto…* en la lista para crear uno en el momento; un proyecto tiene una sola moneda, así que solo se
  ofrecen los proyectos en la moneda del artículo). Luego elige **cómo lo vas a pagar**:
  - *De disponible para asignar* (o *Del balde del proyecto* cuando el proyecto tiene uno): no se aparta nada para él;
  - *De un balde que ya tengo*: elige uno de tus baldes de ahorro en la moneda del artículo. Si el balde deja de
    llenarse por debajo del precio, el formulario te lo dice: el resto del precio saldrá de lo disponible para asignar;
  - *Ahorrar para esto en un balde nuevo*: Jura crea un balde de ahorro con el nombre del artículo, con el precio como
    meta, que se llena con tus ingresos como elijas (un monto fijo cada mes, un porcentaje de cada ingreso o una parte
    de lo que queda). Se cierra cuando compras el artículo, y lo que sobra vuelve a disponible para asignar.

  En *Financiación, fechas y límites* también puedes agregar una fecha para la que lo quieres, las cuentas que pueden
  pagarlo y un enlace.
- **Solo se puede comprar con**: marca las cuentas que pueden pagarlo, por ejemplo solo "Dinero físico"; solo se listan
  las cuentas en la moneda del artículo. Deja todas sin marcar para permitir cualquier cuenta. Si el balde también está restringido a ciertas cuentas, solo se pueden usar las cuentas permitidas por
  ambos.
- Cuando un artículo tiene un balde (propio o de su proyecto), muestra una barra de **% financiado**: cuánto del precio
  cubre el dinero del balde. Los artículos que comparten un balde usan su dinero una sola vez, el más deseado primero, así
  que sus barras suman lo que el balde realmente tiene en lugar de superponerse.

**Comprar.** Presiona el botón de check (*Marcar como comprado*), elige la cuenta y la categoría, y confirma el precio
final. Solo se ofrecen las cuentas en la moneda del artículo (y permitidas para el artículo y su balde). Si no tienes ninguna cuenta en
esa moneda, el diálogo te lo dice; agrega una en **Cuentas** primero. Las listas de cuentas en todo
Jura muestran la moneda de cada cuenta, por ejemplo "Nu · COP". Jura registra el gasto real y, si el artículo tiene un balde, gasta ese dinero del balde en el mismo paso.
Si el balde tiene menos que el precio, paga lo que tiene y el resto sale de lo disponible para asignar. El gasto queda
marcado como pagado desde el balde, así que nunca sale también del balde de gasto de la categoría que elegiste, y sigue
marcado así aunque la cuenta con la que pagaste esté por ahora fuera de tu presupuesto (cuando esa cuenta vuelve al
presupuesto, el balde paga entonces). Un artículo sin balde propio usa el balde de su proyecto, si el proyecto tiene
uno. Como el gasto pertenece a la compra, su balde no se puede cambiar editando el gasto (el campo está bloqueado en el
formulario de edición); deshaz la compra en su lugar.
**Deshacer compra** elimina el gasto definitivamente (no va a la Papelera) y devuelve el dinero al balde.
Eliminar el gasto de la compra desde la página de Transacciones hace lo mismo, salvo que el gasto espera en la Papelera:
restaurarlo marca el artículo como comprado de nuevo, salvo que lo hayas vuelto a comprar mientras tanto (en ese caso el
gasto restaurado es un gasto normal pagado desde el balde de ese artículo).

**Descartar.** El botón de avanzar marca un artículo como *Descartado* (ya no lo quieres) sin eliminarlo. Marca *Mostrar
artículos comprados y descartados* para verlos, y usa la flecha para volver a quererlo.

## Planificador

Menú: **Plan → Planificador**.

El planificador responde "¿qué debo comprar primero y cuándo puedo pagar cada cosa?". Reproduce hacia adelante tus
ingresos y gastos planeados, a través de las reglas de llenado de tus baldes, y te dice la fecha más temprana en que se
puede pagar cada artículo de la lista de deseos con su balde y lo disponible para asignar sin dejar tus cuentas cortas más adelante.
Nada en el planificador cambia tu dinero real. Sigue las mismas reglas que los baldes en vivo, incluidos los llenados fijos
por ciclo, la *parte de lo que queda*, los aumentos y los baldes que se cierran al comprar su artículo.

**Cómo usarlo.**
1. Elige **qué tan seguro debe ser el ingreso**: *Conservador* (solo el ingreso confirmado), *Esperado* (confirmado y
   esperado) u *Optimista* (todo). *Esperado* es el valor por defecto, como en todas partes; elige *Conservador* cuando
   solo quieras contar el dinero seguro. Elige cuánto mirar hacia adelante (12, 24 o 36 meses).
2. Marca los artículos que quieres comprar y presiona **Sugerir el mejor orden**. El planificador propone varios órdenes y marca
   uno como **Recomendado**:
   - **La mejor opción** equilibra cuánto quieres cada artículo con cuánto tiempo espera.
   - **Lo más deseado primero**, **Lo más barato primero**, **Lo más urgente primero** (según tus fechas de "lo quiero para") y **Lo más pronto
     alcanzable primero** son alternativas sencillas para comparar.
3. Para cada orden ves, artículo por artículo, la fecha en que puedes comprarlo, cuánto falta desde hoy, cómo se paga
   (cuánto sale de su balde y cuánto de lo disponible para asignar) y si cae más tarde de lo que querías. Un artículo
   que no se puede comprar a tiempo muestra *No en N meses*, cuánto falta todavía y por qué espera:
   - *Esperando dinero disponible para asignar*: no hay suficiente dinero libre (que no esté en un balde);
   - *Esperando efectivo*: lo disponible para asignar lo cubriría, pero gastos planeados posteriores necesitan el dinero
     de tus cuentas.
   Bajo cada orden ves lo disponible para asignar hoy y lo disponible para asignar proyectado al final del periodo.
4. Encima de los resultados, el planificador dice cuántos de tus ingresos planeados cuenta (por ejemplo *Contando 11 de 12
   ingresos planeados*): los que confía la vista de *qué tan seguro* elegida. Los ingresos vencidos cuentan como si
   llegaran hoy; los ingresos planeados en una cuenta fuera del presupuesto se listan como excluidos.

**Advertencias en un artículo.**
- *Su balde deja de llenarse por debajo del precio*: el balde de ahorro del artículo tiene un tope menor que el precio,
  así que el resto saldrá de lo disponible para asignar.
- *Deja lo disponible para asignar en negativo en una fecha*: comprarlo está bien ese día, pero un gasto planeado
  posterior deja lo disponible para asignar por debajo de cero. Es una advertencia, no un bloqueo: saca algo de dinero
  de un balde o planea la compra más tarde.
- *Bloqueado*: el artículo solo se puede pagar con cuentas de las que su balde nunca recibe ingresos, o solo con cuentas
  fuera del presupuesto.

**Armar tu propio orden.** Presiona **Armar mi propio orden** (o **Ajustar este orden** en una sugerencia). Usa las flechas para
mover artículos antes o después. Los artículos se compran **estrictamente en el orden mostrado**: adelantar uno hace esperar a los demás.
Una fecha opcional de **No antes de** retiene un artículo. El resultado se actualiza a medida que cambias cosas. Un
artículo que no se puede comprar dentro del periodo retendría todo lo que viene después, así que el planificador lo
**aparca**: pasa al final del resultado con una etiqueta *Aparcado* y los artículos siguientes se planean como si no estuviera.

**Guardar y comparar.** Guarda cualquier orden como un plan. Marca dos o más planes guardados para compararlos lado a lado: para
cada artículo ves cuándo se compraría en cada plan, además de un puntaje global (menor es mejor). Cambiar *qué tan seguro*
o *Mirar hacia adelante* recalcula tu orden. Si un artículo de un plan guardado ya se compró o se descartó, al abrir el
plan aparece tachado y queda fuera del cálculo.

**Cómo cuenta el dinero el planificador (avanzado).**
- Trabaja con **lo disponible para asignar**: el dinero de tus cuentas del presupuesto que ningún balde tiene. Parte de
  lo disponible para asignar de hoy y de lo que cada balde tiene hoy, y luego reproduce tu dinero planeado día a día:
  - el ingreso planeado (en una cuenta del presupuesto, o sin cuenta) se reparte como realmente se repartiría: por
    prioridad, con la regla, el tope y las cuentas permitidas de cada balde, o con el reparto propio del plan. Lo que
    ningún balde reclama queda disponible para asignar. El ingreso planeado en una cuenta fuera del presupuesto se excluye;
  - los gastos planeados salen de tus cuentas; uno en una categoría también sale de cada balde de gasto con dinero que
    sigue esa categoría, como lo hace el gasto real;
  - las transferencias planeadas solo cuentan cuando cruzan el borde del presupuesto (hacia o desde una cuenta fuera del presupuesto);
  - los ciclos se cierran con su arrastre (el dinero vuelve a disponible para asignar o pasa a otro balde).
- Un artículo con balde se paga primero desde ese balde y el resto desde disponible para asignar. Un artículo sin balde
  se paga desde disponible para asignar. Los artículos que comparten un balde comparten su dinero.
- Una compra también necesita el **efectivo**: el planificador solo compra un artículo en un día en que tus cuentas
  todavía lo cubren después de cada gasto planeado posterior (mira el saldo más bajo desde ese día en adelante). Así que
  un artículo puede esperar aunque lo disponible para asignar lo cubra hoy, porque una factura más adelante en el mes necesita ese dinero.
- Los intereses proyectados nunca se cuentan.
- Un artículo que solo se puede comprar con ciertas cuentas, mientras que su balde solo recibe ingresos de otras cuentas,
  aparece como **Bloqueado**, y también uno que solo pueden pagar cuentas fuera del presupuesto.
- Si ninguno de tus baldes tiene una regla de llenado, el planificador te avisa: el ingreso planeado queda entonces en
  lo disponible para asignar, que igual paga tus artículos.

## Reportes

Menú: **Informes** (Mensual, Anual, Personalizado, Pronóstico, Informe de baldes, Informe de proyectos).

| Reporte | Qué muestra | Exportación |
|---|---|---|
| **Mensual** | totales, gastos por categoría y por cuenta de un mes | PDF |
| **Anual** | el año mes a mes, con tendencias | PDF |
| **Personalizado** | cualquier rango de fechas que elijas | CSV |
| **Pronóstico** | tu patrimonio neto real mes a mes, seguido de saldos proyectados | ninguna |
| **Informe de baldes** | cómo se financian tus baldes, el dinero que entra y sale, y hacia dónde van | ninguna |
| **Informe de proyectos** | los totales deseados, comprados y financiados de cada proyecto y cuándo se podría completar | ninguna |

Mensual, Anual y Personalizado muestran datos **reales y registrados**. Los totales se mantienen separados por moneda. El reporte mensual divide los gastos
en **fijos** (registrados automáticamente, o confirmados desde un plan repetido) y **variables** (todo lo demás).

**Pronóstico.** Una línea continua muestra tu patrimonio neto real de los últimos seis meses. Las líneas punteadas continúan desde "Hoy" durante
los próximos seis meses, una por cada nivel de confianza en tus ingresos planeados (Conservador, Esperado, Optimista). La tabla
de abajo lista los ingresos planeados, los gastos planeados y el saldo de cada vista por mes. Punteado siempre significa *estimación*.

**Reporte de baldes.** *Fondos disponibles hoy* compara tus baldes, *Dinero que entra y sale, por mes* muestra las asignaciones y
los gastos de los baldes durante seis meses, y la tabla proyecta cada balde 12 meses hacia adelante usando la vista que elijas en
la parte superior (reproduce tu ingreso planeado a través de tus reglas de llenado, como el planificador).

**Reporte de proyectos** (Informes → Informe de proyectos). Para cada proyecto (y los artículos sin proyecto): lo que todavía se desea, lo que ya compraste, cuánto
está financiado hoy, y el mes en que se podría comprar todo si sigues el mejor orden del planificador durante los próximos 24
meses, usando la vista elegida de tus ingresos. Los baldes compartidos se cuentan una vez (el artículo más deseado primero), y las
fechas salen de un solo plan para todos tus artículos deseados, así que dos proyectos nunca cuentan con el mismo dinero libre. Un proyecto
muestra el presupuesto que le queda (presupuesto - lo que realmente costaron sus compras - lo que todavía se desea) y, cuando tiene una fecha
objetivo, si el último artículo se compra a tiempo y cuánto apartar cada mes para lograrlo.

## Notificaciones

La campana en la barra superior reúne las alertas. Marca una o todas como leídas desde ahí.

- **Umbral de presupuesto / presupuesto superado**: has usado la mayor parte del límite de un balde de gasto, o te pasaste.
- **Balde de ahorro atrasado**: un balde de ahorro con fecha objetivo se está atrasando respecto a ella (se revisa cada
  lunes). Avisa una vez, y de nuevo solo después de que lo leas y el balde siga atrasado.
- **Pago planeado próximo**: un gasto planeado vence en 3 días o menos (contados en tu zona horaria). Dice si se
  **registrará automáticamente** o si tienes que **confirmarlo** cuando lo pagues. Los planes en una cuenta archivada
  quedan fuera.
- **No se pudo registrar automáticamente**: un plan configurado para registrarse solo no se pudo registrar, con el motivo
  (su cuenta está archivada o eliminada, su categoría fue eliminada). Se te avisa una vez por motivo, y una vez por todo
  un plan repetido en lugar de por cada una de sus fechas; se vuelve a intentar cada hora.
- **Saldo bajo**: el saldo de una cuenta ha bajado de unos tres días de tu gasto habitual, o por debajo de cero.

Se te notifica una vez por cada cruce; una alerta sin leer no se repite.

## Preferencias, perfil y apariencia

- **Configuración → Preferencias**: **moneda** principal, **idioma** (inglés o español) y **zona horaria**. Estos
  controlan cómo se muestran los montos, las fechas y el texto en todas partes. El cambio de idioma se aplica justo después de guardar, sin recargar. Las notificaciones también se escriben en tu idioma,
  incluso las que se envían mientras no estás.
  La zona horaria decide qué es "hoy": las transacciones y planes nuevos usan por defecto la fecha de hoy donde vives,
  los planes se vencen, los planes que se registran solos se registran y los ciclos de los baldes se cierran cuando cambia
  el día allí, y el panel y los reportes se abren en tu mes actual.
  Debajo, **Cómo se ven las fechas, horas y números** te permite elegir el estilo de fecha (2026-03-09, 03/09/2026,
  09/03/2026 o 9 mar 2026), el formato de hora de 12 o 24 horas, los separadores decimal y de miles, el número de decimales, y
  si la moneda se muestra como un símbolo (`$`), un código (`COP`) o nada, antes o después del monto. Los botones de
  números definen combinaciones comunes con un clic y la vista previa muestra el resultado. Cada opción puede quedarse en
  **Seguir mi idioma**. La elección se aplica a cada página, gráfico, reporte en PDF y notificación; las exportaciones CSV siempre conservan
  fechas ISO y números simples para que otros programas puedan leerlas.
- **Configuración → Perfil y seguridad**: cambia tu nombre, correo electrónico y contraseña, o elimina tu cuenta.
- **Modo oscuro**: alterna entre claro y oscuro desde la barra superior. La barra superior también muestra tu patrimonio neto y la campana de notificaciones.
- **Papelera** (Configuración → Papelera): las transacciones, cuentas y categorías eliminadas esperan aquí. **Restaurar** las trae de vuelta
  (ambos lados de una transferencia juntos) y **Eliminar definitivamente** las quita para siempre. Una cuenta o categoría que
  todavía es usada por otros registros, incluidos los eliminados, aún no se puede eliminar definitivamente.
- **Exportar mis datos**: aún no está disponible.

## Preguntas frecuentes

**¿A dónde se fueron las metas de ahorro?** Ahora son **baldes de ahorro** (Plan → Baldes), y `/planning/goals` te lleva
ahí. El monto objetivo y la fecha objetivo de una meta pasaron a ser la meta y la fecha objetivo del balde, y cada aporte
pasó a ser dinero apartado en el balde en su fecha, así que cuenta en los baldes y sale de lo *disponible para asignar*.
Las metas que habías completado o archivado pasaron a ser baldes completados cuyo dinero se liberó de nuevo (su historial
se conserva); las metas que habías eliminado no se trasladaron. Como los aportes se copiaron completos, lo *disponible
para asignar* puede mostrar que asignaste más de lo que tienes: la campana tiene un aviso sobre eso, y el monto se muestra
en rojo hasta que saques algo de dinero de un balde o agregues dinero a tus cuentas.

**Mi saldo se ve mal.** Los saldos se calculan a partir de tu saldo inicial y cada transacción. Revisa el
saldo inicial de la cuenta y luego busca una transacción faltante o duplicada.

**¿Por qué no puedo eliminar una categoría?** Es una categoría del sistema o ya la usan transacciones. Crea una
nueva y úsala de ahora en adelante.

**¿Por qué no puedo eliminar una cuenta?** Las cuentas con historial se archivan en lugar de eliminarse, para que los reportes antiguos sigan
siendo precisos.

**Una transferencia muestra dos líneas.** Es normal: una por la cuenta de la que salió el dinero y otra por la que llegó.

**¿Por qué veo totales separados para cada moneda?** Jura no convierte entre monedas, así que nunca
las mezcla.

**¿Qué es "disponible para asignar"?** El dinero de tus cuentas del presupuesto que todavía no tiene ningún balde. Cada
peso está en un balde o disponible para asignar; dale una tarea moviéndolo a un balde. Siempre se calcula a partir de tus
saldos y baldes, nunca se escribe a mano.

**Disponible para asignar está en rojo (asignado de más).** Tus baldes tienen más que tus cuentas: un gasto, una
transferencia a una cuenta fuera del presupuesto, un ingreso que eliminaste después de usar su dinero, o una asignación de
más que confirmaste. Saca dinero de un balde (*Mover dinero → Disponible para asignar*) o registra el ingreso que falta.

**Un balde dice Sobregastado.** Se pagó con él más de lo que tenía. Mueve dinero hacia él, desde otro balde o desde
disponible para asignar.

**¿A dónde se fueron las transacciones recurrentes?** Pasaron a ser planes repetidos en **Plan → Próximos y flujo de caja**,
bajo **Planes repetidos**. Cada una conservó su cuenta, categoría, monto y ritmo, y se registra sola en su fecha
(**Registrarlo automáticamente en la fecha** está activado), conservando la próxima fecha en que vencía (una del 29 al 31
cuya próxima fecha se había corrido al 28, o al 30, volvió a su propio día en ese mes; esto también pasa si tú mismo
habías movido esa próxima fecha al 28 a 30, así que revísala en **Planes repetidos**). Si una estaba atrasada, cada fecha
que se pasó se convirtió en un plan que se registra en su propia fecha la próxima vez que la aplicación registre planes.
Una que ya no podía registrarse sola porque su cuenta se archivó o se eliminó, o su categoría se eliminó, no se puso al
día: aparece **En pausa** sin fechas; apúntala a una cuenta y categoría activas y reanúdala. Una pausada o terminada
también aparece **En pausa**. Las transacciones que creó antes siguen marcadas como registradas
automáticamente, y el enlace del menú anterior te lleva a la nueva lista.

**¿A dónde se fue mi balde "Sin asignar"?** Se reemplazó por disponible para asignar, que se calcula en lugar de guardarse.
Lo que tenía ahora simplemente está disponible para asignar, y los baldes que le enviaban sus sobrantes ahora los devuelven a disponible para asignar.

## Límites de esta versión

- Los intereses existen solo en las cuentas, nunca en los baldes, y son solo proyectados; regístralos como una transacción real cuando el
  banco los pague. El monto de llenado de un balde no puede ser distinto solo en el primer mes, y un aumento siempre empieza en el
  ciclo siguiente.
- Jura nunca convierte entre monedas. Cada moneda se planea, se proyecta y se reporta por separado.
- Solo se proyectan los gastos planeados: un gasto planeado en una categoría sale de los baldes que la siguen, pero el
  gasto que no has planeado no se predice.
- Algo que ya ocurrió y se repite todavía no se puede registrar y planear en un solo paso: regístralo como una
  transacción y luego agrega un plan repetido que empiece en la siguiente fecha.
- Los artículos que comparten un balde se comparan cada uno con el mismo fondo, así que sus barras de "financiado" pueden superponerse.
- Las pantallas, mensajes, etiquetas, exportaciones y PDF siguen tu preferencia de idioma (inglés o español). Los nombres que escribes tú mismo (cuentas, categorías, baldes) se muestran tal como los escribiste.
