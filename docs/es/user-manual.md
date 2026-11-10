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
7. [Flujo de caja (ingresos y gastos planeados)](#flujo-de-caja-ingresos-y-gastos-planeados)
8. [Baldes (presupuestos y ahorros)](#baldes-presupuestos-y-ahorros)
9. [Lista de deseos y proyectos](#lista-de-deseos-y-proyectos)
10. [Planificador](#planificador)
11. [Metas de ahorro](#metas-de-ahorro)
12. [Gastos recurrentes](#gastos-recurrentes)
13. [Reportes](#reportes)
14. [Notificaciones](#notificaciones)
15. [Preferencias, perfil y apariencia](#preferencias-perfil-y-apariencia)
16. [Preguntas frecuentes](#preguntas-frecuentes)
17. [Límites de esta versión](#límites-de-esta-versión)

---

## Primeros pasos

Los visitantes que no han iniciado sesión ven una página de bienvenida en la raíz del sitio con los botones **Iniciar sesión** y **Empieza gratis**; los usuarios con sesión iniciada van directo al panel.

1. **Regístrate** y verifica tu correo electrónico. La mayoría de las páginas requieren una cuenta verificada.
2. **Responde el asistente de configuración.** La primera vez que entras a Jura te hace cuatro preguntas cortas, y puedes saltar cualquiera:
   - tu idioma, moneda principal y zona horaria;
   - tu primera cuenta y su saldo actual;
   - tu ingreso principal, cada cuánto llega y la próxima fecha de pago (se guarda como ingreso planeado en Flujo de caja, así alimenta la proyección sin cambiar ningún saldo);
   - las categorías que quieres presupuestar cada mes, con un límite para cada una (se guardan como baldes), y una meta de ahorro opcional.

   **Saltar configuración** cierra el asistente en cualquier momento. Cuando lo terminas o lo saltas, no vuelve a aparecer; todo lo que pregunta se puede cambiar después desde su propia página. Si cierras el navegador a medias, se abre de nuevo la próxima vez que entres.
3. **Registra tu primera transacción.** El panel te guía cuando aún no tienes datos.
4. Puedes cambiar tu **moneda, idioma y zona horaria** cuando quieras en Configuración → Preferencias.

Tus datos son privados. Nadie más puede ver ni abrir tus cuentas, transacciones o presupuestos.

## Panel

Es la primera página después de iniciar sesión. Responde "¿cómo voy este mes?" de un vistazo:

- **Patrimonio neto total**: la suma de los saldos de tus cuentas.
- **Ingresos de este mes**, **Gastos de este mes** y **Ahorro de este mes**.
- **Gasto diario promedio**, **Mayor gasto del mes** y **Categoría con más gasto**.
- **Días de autonomía financiera**: cuántos días te alcanzaría tu dinero disponible a tu ritmo de gasto actual
  si no llegara ningún ingreso nuevo.
- Una tarjeta de **Próximos 30 días** con los ingresos planeados, los gastos planeados y el neto, claramente marcados como planeados y aún no reales
  (aparece solo cuando tienes eventos planeados; el ingreso cuenta cuando está confirmado o esperado).
- Breves **insights** en lenguaje sencillo, por ejemplo "Gastaste 12% más que el mes pasado".
- Gráficos: **Gastos por categoría** y la **Evolución mensual**.

Los montos en distintas monedas nunca se suman; cada moneda se muestra por separado.

## Cuentas (Bancos y billeteras)

Menú: **Cuentas → Bancos y billeteras**.

Una cuenta es cualquier lugar que guarda dinero: una cuenta bancaria, una billetera digital, efectivo, una tarjeta de crédito o una cuenta de ahorros.

- **Agrega una cuenta** con un nombre, tipo, saldo inicial y moneda. También puedes indicar un banco o institución, color,
  ícono, número parcialmente oculto y notas.
- **Intereses (rendimiento).** Si la cuenta genera intereses, ingresa la **tasa de interés anual** y cuándo se acredita
  (diaria, mensual o anual). El pronóstico del flujo de caja mostrará entonces una columna proyectada de **Intereses**. Los intereses existen solo
  en cuentas reales, nunca en baldes, y son solo una proyección: no se registra nada hasta que tú agregues los intereses como una
  transacción real. No planees además los mismos intereses como un ingreso planeado, o se contarán dos veces.
- **El saldo se calcula por ti** a partir del saldo inicial más cada transacción. Nunca escribes el saldo
  actual directamente. Para corregirlo, agrega una transacción.
- **Eliminar una cuenta:** si no tiene transacciones, se elimina. Si tiene transacciones, se **archiva**
  (se oculta para nuevos registros pero se conserva en tu historial) para que tus reportes sigan siendo correctos.

## Transacciones

Menú: **Finanzas → Transacciones** (y los atajos **Ingresos**, **Gastos** y **Transferencias**).

Hay tres tipos:

| Tipo | Úsalo cuando | Efecto |
|---|---|---|
| **Ingreso** | recibes dinero | aumenta el saldo de la cuenta |
| **Gasto** | gastas dinero | disminuye el saldo de la cuenta |
| **Transferencia** | mueves dinero entre tus propias cuentas | disminuye una cuenta y aumenta la otra; no cuenta como ingreso ni como gasto |

Para agregar una, usa el botón de agregar (está disponible desde cualquier página). Elige la cuenta, categoría, monto, fecha y
una **hora** opcional (por defecto es la actual y se puede borrar), una descripción opcional, notas y método de pago (efectivo, tarjeta débito, tarjeta de crédito, transferencia bancaria u otro).

- Puedes **editar** o **eliminar** cualquier transacción; los saldos se actualizan automáticamente.
- **Filtra** la lista por tipo para ver solo ingresos, gastos o transferencias.
- Editar o eliminar un lado de una transferencia actualiza también el otro lado. Cada transferencia aparece dos veces en la lista completa:
  la fila dice **Transferencia saliente → (destino)** en la cuenta de origen y **Transferencia entrante ← (origen)** en la cuenta de destino.
- Dentro de un día, las transacciones se listan de la hora más reciente a la más antigua; las que no tienen hora van al final. La hora se muestra en el formato
  que elegiste en Preferencias (12 o 24 horas).

## Seleccionar varias filas a la vez

Las listas largas (transacciones, eventos planeados, lista de deseos, gastos recurrentes, metas de ahorro, baldes) tienen una casilla en
cada fila y una casilla **Seleccionar todo**. Al seleccionar filas se abre una barra con **Eliminar** y **Limpiar**.

- En **Transacciones** la barra también ofrece **Cambiar categoría / cuenta** para las filas seleccionadas (las transferencias nunca se
  modifican, y una categoría solo aplica a transacciones del mismo tipo) y, una vez que marcas toda la página, **Seleccionar
  los N que coinciden** para actuar sobre todas las filas que cumplen tus filtros, no solo las de esta página.
- Después de eliminar transacciones, un aviso ofrece **Deshacer**; las transacciones eliminadas también esperan en **Configuración → Papelera**.
- Eliminar eventos planeados quita solo las ocurrencias elegidas de un evento repetitivo. Eliminar baldes mueve su dinero
  al balde por defecto. Las metas eliminadas van a **Metas eliminadas**, donde se pueden restaurar.

## Importar desde CSV

Menú: **Finanzas → Importar CSV**.

Úsalo para traer historial desde una hoja de cálculo o una exportación de tu banco.

1. Descarga la **plantilla** desde la página de importación si quieres un archivo ya preparado.
2. Sube tu CSV. Jura lo lee y muestra una **vista previa** con los problemas señalados antes de guardar nada.
3. Confirma para importar las filas válidas.

Reglas:
- Columnas obligatorias: **fecha, cuenta, categoría, tipo, monto**. Opcionales: descripción, notas, **hora** (como `14:30` o `2:05 PM`).
- Los nombres de las columnas pueden estar en inglés o en español; también se entienden alternativas comunes, como "valor" para el monto.
- Hasta **1000 filas** por archivo.
- Los nombres de cuentas y categorías deben coincidir con los que ya tienes.

## Categorías

Menú: **Configuración → Categorías**.

Las categorías agrupan tus ingresos y gastos (por ejemplo Alimentación, Salario). Jura incluye un conjunto de **categorías
del sistema** compartidas por todos, y puedes agregar las tuyas.

- Una categoría es de **Ingreso** o de **Gasto**.
- Las categorías del sistema, y las que ya tienen transacciones, **no se pueden editar ni eliminar**, para que tu
  historial nunca cambie de significado. Crea una categoría nueva en su lugar.
- Tus propias categorías tienen una casilla, además de botones para editar y eliminar. Marca varias (o **Seleccionar todo**) para eliminarlas
  juntas; las que ya tienen transacciones se conservan, y las eliminadas van a la **Papelera**.

## Flujo de caja (ingresos y gastos planeados)

Menú: **Planificación → Flujo de caja**.

Usa esta página para anotar el dinero que esperas recibir o gastar en el futuro, por irregular que sea, y ver cómo
podría verse tu saldo. Los eventos planeados son **estimaciones**: nunca cambian tus saldos reales ni tus reportes
hasta que los marques como hechos.

**Agregar un evento planeado.** Presiona **Esperar ingreso** o **Planificar un evento**, y luego completa qué es, el monto, la
fecha y qué tan seguro estás. En **Más opciones** puedes elegir una cuenta y una categoría, agregar notas, o hacer que se
**repita** (semanal, cada dos semanas, mensual o anual), opcionalmente hasta una fecha. Los eventos repetitivos se crean
con hasta 12 meses de anticipación y se extienden automáticamente.

**¿Qué tan seguro estás?**
- **Confirmado**: va a suceder (un contrato firmado, una factura fija).
- **Esperado**: probable, pero no seguro.
- **Especulativo**: una esperanza o un tal vez.

**Tres vistas del futuro** (el selector en la parte superior de la página):
- **Conservador**: solo cuenta el ingreso confirmado.
- **Esperado**: cuenta el ingreso confirmado y el esperado.
- **Optimista**: cuenta todo el ingreso planeado.

Los gastos planeados siempre cuentan completos en todas las vistas. El gráfico y la tabla muestran, mes a mes, los ingresos
planeados, los gastos planeados y tu saldo proyectado, por moneda. El saldo de hoy es tu patrimonio neto real.

**Cuando el dinero realmente se mueve.**
- Presiona el botón de **check** (*Marcar como hecho*) para registrar la transacción real a partir del plan. Antes puedes ajustar el
  monto, la fecha, la cuenta y la categoría.
- Si ya registraste tú mismo una transacción similar, el plan muestra **Parece hecho: ...** con un botón **Sí, vincularlo**.
  Nunca se vincula nada sin tu confirmación.
- Presiona **Omitir** para algo que no va a suceder. Un plan omitido permanece en la lista (atenuado) con **Reabrir**
  para volver a planearlo y **Eliminar** para quitarlo.
- **Eliminar** quita un plan definitivamente. Eliminar una ocurrencia de un evento repetitivo nunca la trae de vuelta.

**Cambiar eventos repetitivos.** Al editar o eliminar una ocurrencia, se te pregunta si es **solo este** o **este
y todos los siguientes**. Las ocurrencias anteriores, y las ya hechas, nunca se tocan. Cuando cambias "todos los siguientes",
las fechas se mantienen como están; para mover una fecha, edita esa ocurrencia individual.

**Intereses.** Las cuentas con tasa de interés agregan una línea de intereses proyectados a cada mes (los ingresos y
gastos planeados en esa cuenta mueven su saldo en sus fechas, así que los intereses los siguen).

**Avanzado.** Un plan vencido que sigue abierto cuenta en el mes actual. Las distintas monedas se proyectan
por separado y nunca se suman.

## Baldes (presupuestos y ahorros)

Menú: **Planificación → Baldes**. (La antigua página de **Presupuestos** ahora vive aquí; su dirección redirige.)

Un **balde** es dinero que apartas para algo. Los baldes solo *reservan* dinero: tus cuentas y saldos reales
nunca cambian. Hay dos tipos:

- **Límite de gasto**: elige una categoría y un límite por mes o año. La tarjeta muestra cuánto has gastado, cuánto
  queda, y te avisa al llegar al 80% y cuando te pasas. Esto es lo que antes eran los presupuestos.
- **Ahorro**: un fondo para una meta (un carro, un viaje, un fondo de emergencia), con una meta opcional. La tarjeta muestra lo que hay
  disponible y qué tan cerca estás de la meta.

**Llenar baldes con el ingreso.** Abre un balde y, en *Llenado y opciones avanzadas*, elige cómo se llena cuando
llega un ingreso:
- **Porcentaje de cada ingreso**: por ejemplo el 20% de cada ingreso.
- **Monto de cada ingreso**: por ejemplo 150.000 de cada ingreso. Si el balde tiene un ciclo, el monto es por ciclo: el
  ingreso completa el balde hasta ese valor, así que tres pagos en un mes siguen dando 150.000 en total, no 450.000.
- **Porcentaje de lo que queda al llegar a este balde**: un porcentaje del ingreso que todavía no ha sido reclamado cuando se llega a este balde en el
  orden de prioridad. Ponlo después de los baldes de monto fijo: por ejemplo Compra 80,95% de lo que queda (17/21) y
  Restauración 100% de lo que queda; lo que el balde de Compra no pueda recibir (porque está lleno) pasa a Restauración.
- **Llenar hasta su meta**: completa lo que falte.

- **Súbelo después** (opciones avanzadas): desde el ciclo *siguiente* a que otro balde alcance su meta (o se complete),
  un llenado fijo o por porcentaje cambia a un nuevo valor. Ejemplo: Ahorro general 150.000 por mes, y luego 300.000 una vez que el
  fondo de Compra esté lleno. Entra en vigor desde el ciclo siguiente, nunca a mitad de ciclo.

A cada ingreso, los baldes se ofrecen en **orden de prioridad** (primero P0, luego P1, y así sucesivamente), de modo que los importantes se llenen antes que
el resto. Un balde con meta deja de llenarse una vez alcanzada la meta. Lo que ningún balde reclama va al
**balde por defecto** (llamado *Sin asignar* hasta que elijas uno propio). El llenado ocurre solo, pero **únicamente cuando al menos
un balde tiene una regla de llenado**; sin reglas, no se asigna nada. Si editas o eliminas un ingreso, sus llenados se
rehacen o se quitan.

**Decidir para un ingreso concreto.** En **Flujo de caja**, cuando planeas un ingreso, *Más opciones* te permite escribir exactamente cuánto
va a cada balde solo para ese ingreso. El resto va al balde por defecto.

**Mover dinero.**
- **Mover dinero** (el ícono de flechas) transfiere fondos de un balde a otro de la misma moneda.
- **Saldo inicial** (en el formulario del balde) es el dinero que un balde ya tiene cuando empiezas. Cámbialo o bórralo
  en cualquier momento; solo afecta al balde, nunca a tus cuentas reales.
- **Agregar o quitar fondos** (el ícono de billetes) corrige un saldo a mano. Usa un monto negativo para quitar, hasta
  el monto financiado.
- **Toda la actividad, editar o quitar movimientos** (en *Actividad reciente*) lista todo lo que alguna vez entró o salió del
  balde. Los movimientos que hiciste tú mismo (saldo inicial, ajustes, traslados entre baldes) tienen un botón de papelera, y
  **Poner en cero** vacía el balde. Los movimientos creados por un ingreso o por el cierre de un ciclo no se pueden quitar.
- Eliminar un balde devuelve su dinero al balde por defecto.

**Ciclos.** Un balde de gasto funciona en ciclos: define **Ciclo: se repite cada N días / semanas / meses / años**, y
opcionalmente una **fecha de inicio** (por ejemplo el 15, para seguir tu día de pago; vacío significa meses calendario). Un mes
corto nunca lo rompe: un ciclo que empieza el 31 empieza el 28 en febrero.

**Qué pasa durante un ciclo.** Digamos que ganas 1000 al mes, y **Mercado** tiene un límite de 500 en **P0**, se llena hasta
su meta y envía su sobrante a **Emergencia** (meta 1000, **P10**):
1. Cuando empieza el mes, la tarjeta del balde muestra lo que se **espera** (en cursiva): 500 para el mercado y 500 para el
   fondo de emergencia, tomados del ingreso que planeaste en Flujo de caja. Es solo una estimación, aún no se guarda nada. Cuando
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

**Fin del ciclo (avanzado).** Elige qué pasa con lo que sobra: **dejarlo** en el balde, **devolverlo** al
balde por defecto, o **moverlo** a otro balde (el balde de excedente).

**Solo desde ciertas cuentas (avanzado).** Marca cuentas para que un balde se llene solo con el ingreso que llega a ellas, por
ejemplo un fondo de "Dinero físico". El ingreso en otras cuentas lo omite.

**Gasto planeado.** Los gastos que planeas en **Flujo de caja** para la categoría de un balde de gasto aparecen en su tarjeta como
**planeado**, junto con lo que quedaría **después de lo planeado**.

**Completar un balde.** Cuando una meta está cumplida, un balde de ahorro se puede **completar**: lo que queda en él pasa a
su balde de "Mover el sobrante a" (o al balde por defecto) y deja de recibir ingresos, de modo que el ingreso sigue hacia los
siguientes baldes. Hay dos maneras:
- **Automáticamente**: en el formulario del balde (*Llenado y opciones avanzadas*) marca *Cerrar este balde cuando se compre un artículo de la lista de deseos pagado con él*, y define *Sobrante al final del ciclo* como *Moverlo a otro balde* y elige adónde. Comprar el artículo desde la
  lista de deseos completa entonces el balde. El **Planificador** hace lo mismo en la fecha de compra cuando simula.
- **A mano**: **Marcar como completado** en la tarjeta del balde. **Reabrir** lo pone a trabajar de nuevo.

Deshacer la compra de un artículo de la lista de deseos reabre el balde, pero el sobrante que ya se movió se queda donde fue.

**Ejemplo práctico: ahorrar para un carro.** Ganas 4.500.000 en el mes 1 (tres pagos) y 2.000.000 al mes después de
eso, y quieres dinero para ocio, un fondo de ahorro general, un fondo de 10.000.000 para el carro y un fondo para reparaciones:

| Balde | Tipo | Configuración |
| --- | --- | --- |
| Ocio | Límite de gasto | Categoría *Ocio*, límite 150.000 al mes, **P0**, llenado: monto fijo 150.000 |
| Ahorro general | Ahorro | Ciclo: cada 1 mes, **P1**, llenado: monto fijo 150.000, *Súbelo después*: 300.000 cuando el *Fondo de compra* esté lleno |
| Fondo de compra | Ahorro | Meta 10.000.000, **P2**, sin ciclo, llenado: parte de lo que queda 80,95% (es decir 17/21), marca *Cerrar este balde cuando se compre un artículo de la lista de deseos pagado con él*, el sobrante pasa a *Restauración* |
| Restauración | Ahorro | **P3**, sin ciclo, llenado: parte de lo que queda 100% |

Luego agrega un **Renault 21** a la lista de deseos por 10.000.000 usando el balde *Fondo de compra*, y planea tus ingresos en
**Flujo de caja**. Esto es lo que verás:
- Cada mes, Ocio y Ahorro general toman 150.000 una sola vez, sin importar cuántos pagos lleguen. Lo que queda se reparte 17/21
  al Fondo de compra y el resto a Restauración. Cuando el Fondo de compra está lleno, el resto va a Restauración.
- En el **Planificador**, *Sugerir el mejor orden* te da el mes en que puedes comprar el carro. Cuando realmente lo compras desde la
  lista de deseos, el Fondo de compra se cierra, lo que supere 10.000.000 pasa a Restauración, y Ahorro general sube a
  300.000 desde el mes siguiente.
- En **Flujo de caja**, una cuenta con tasa de interés (ver *Cuentas*) agrega una columna proyectada de *Intereses*, así que el
  pronóstico incluye lo que rinden las "cajitas" de tu banco.

## Lista de deseos y proyectos

Menú: **Planificación → Lista de deseos**.

Un lugar para todo lo que te gustaría conseguir. Agrupa las cosas en **proyectos** (un estudio en casa, un viaje), **pasatiempos**
(ciclismo) o cualquier otra agrupación, y califica cuánto quieres cada una.

- **Nuevo proyecto** crea un grupo con un nombre, un tipo y un color. Eliminar un proyecto conserva sus artículos bajo
  "Sin proyecto".
- **Nuevo artículo** necesita un nombre y un precio. Define **cuánto lo quieres** (de 1 a 5), y en *Financiación, fechas y límites*
  puedes agregar un **balde** desde el cual pagarlo, una fecha para la que lo quieres y un enlace.
- **Solo se puede comprar con**: marca las cuentas que pueden pagarlo, por ejemplo solo "Dinero físico". Deja todas
  sin marcar para permitir cualquier cuenta. Si el balde también está restringido a ciertas cuentas, solo se pueden usar las cuentas permitidas por ambos.
- Cuando un artículo está vinculado a un balde, muestra una barra de **% financiado**: el dinero disponible del balde comparado con el
  precio. Los artículos que comparten un balde se comparan cada uno con el mismo fondo, así que sus barras se superponen en lugar de sumarse.

**Comprar.** Presiona el botón de check (*Marcar como comprado*), elige la cuenta y la categoría, y confirma el precio
final. Jura registra el gasto real y, si el artículo tiene un balde, gasta ese dinero del balde en el mismo paso.
Si el balde tiene menos que el precio, paga lo que tiene y el resto simplemente se paga desde tu cuenta.
**Deshacer compra** elimina el gasto y devuelve el dinero al balde.

**Descartar.** El botón de avanzar marca un artículo como *Descartado* (ya no lo quieres) sin eliminarlo. Marca *Mostrar
artículos comprados y descartados* para verlos, y usa la flecha para volver a quererlo.

## Planificador

Menú: **Planificación → Planificador**.

El planificador responde "¿qué debo comprar primero y cuándo puedo pagar cada cosa?". Reproduce el ingreso que has
planeado a través de las reglas de llenado de tus baldes y te dice la fecha más temprana en que se puede pagar cada artículo de la lista de deseos.
Nada en el planificador cambia tu dinero real. Sigue las mismas reglas que los baldes en vivo, incluidos los llenados fijos
por ciclo, la *parte de lo que queda*, los aumentos y los baldes que se cierran al comprar su artículo.

**Cómo usarlo.**
1. Elige **qué tan seguro debe ser el ingreso**: *Conservador* (solo el ingreso confirmado), *Esperado* (confirmado y
   esperado) u *Optimista* (todo). Conservador es el valor por defecto, para que el planificador nunca prometa dinero que podría no
   llegar. Elige cuánto mirar hacia adelante (12, 24 o 36 meses).
2. Marca los artículos que quieres comprar y presiona **Sugerir el mejor orden**. El planificador propone varios órdenes y marca
   uno como **Recomendado**:
   - **La mejor opción** equilibra cuánto quieres cada artículo con cuánto tiempo espera.
   - **Lo más deseado primero**, **Lo más barato primero**, **Lo más urgente primero** (según tus fechas de "lo quiero para") y **Lo más pronto
     alcanzable primero** son alternativas sencillas para comparar.
3. Para cada orden ves, artículo por artículo, la fecha en que puedes comprarlo (o *No en N meses* con cuánto falta todavía),
   cuánto falta desde hoy, y si cae más tarde de lo que querías.

**Armar tu propio orden.** Presiona **Armar mi propio orden** (o **Ajustar este orden** en una sugerencia). Usa las flechas para
mover artículos antes o después. Los artículos se compran **estrictamente en el orden mostrado**: adelantar uno hace esperar a los demás.
Una fecha opcional de **No antes de** retiene un artículo. El resultado se actualiza a medida que cambias cosas.

**Guardar y comparar.** Guarda cualquier orden como un plan. Marca dos o más planes guardados para compararlos lado a lado: para
cada artículo ves cuándo se compraría en cada plan, además de un puntaje global (menor es mejor).

**Cómo cuenta el dinero el planificador (avanzado).**
- Parte de lo que cada balde tiene disponible hoy, y luego suma el ingreso planeado tal como realmente se repartiría: por
  prioridad, con la regla, la meta y las cuentas permitidas de cada balde. El dinero que ningún balde reclama va al balde por defecto.
- Un artículo se paga desde su balde, o desde el balde por defecto si no tiene ninguno. Los artículos que comparten un balde comparten su dinero.
- Un artículo que solo se puede comprar con ciertas cuentas, mientras que su balde solo recibe ingresos de otras cuentas,
  aparece como **Bloqueado**.
- Si ninguno de tus baldes tiene una regla de llenado, el planificador te avisa: el ingreso no llega a ningún balde, así que solo se usa el dinero
  que ya está en los baldes.
- Los gastos planeados no se descuentan del dinero de los baldes en esta versión, y no se predice el gasto en una categoría.

## Metas de ahorro

Menú: **Planificación → Metas de ahorro**.

Una meta es algo para lo que estás ahorrando, con un monto objetivo y una fecha objetivo opcional.

- **Registra un aporte** cada vez que apartes dinero. Un aporte se puede vincular a una transacción.
- La meta muestra el avance hacia su objetivo. Cuando los aportes alcanzan el objetivo, se marca como **completada**.
- Las metas pueden estar **activas**, **completadas** o **archivadas**.
- Una meta eliminada aparece en **Metas eliminadas**, al final, donde puedes restaurarla o eliminarla definitivamente.
- Si una meta se está atrasando respecto a su fecha objetivo, recibes una notificación.

## Gastos recurrentes

Menú: **Planificación → Gastos recurrentes**.

Para ingresos o gastos que se repiten (arriendo, salario, suscripciones). Elige la cuenta, la categoría, el monto, la frecuencia
(semanal, quincenal, mensual o anual), la próxima fecha de pago y una fecha de fin opcional.

- Jura **crea la transacción real por ti** cuando llega la fecha. Esto se ejecuta una vez al día, por la mañana.
- Usa el **interruptor de encendido/apagado** para pausar uno sin eliminarlo. Se apaga solo después de su fecha de fin.
- Recibes un recordatorio **3 días antes** de que venza un pago.
- Las transacciones creadas de esta forma quedan marcadas como generadas a partir de un movimiento recurrente.

## Reportes

Menú: **Reportes**.

| Reporte | Qué muestra | Exportación |
|---|---|---|
| **Mensual** | totales, gastos por categoría y por cuenta de un mes | PDF |
| **Anual** | el año mes a mes, con tendencias | PDF |
| **Personalizado** | cualquier rango de fechas que elijas | CSV |
| **Pronóstico** | tu patrimonio neto real mes a mes, seguido de saldos proyectados | ninguna |
| **Baldes** | cómo se financian tus baldes, el dinero que entra y sale, y hacia dónde van | ninguna |
| **Proyectos** | los totales deseados, comprados y financiados de cada proyecto y cuándo se podría completar | ninguna |

Mensual, Anual y Personalizado muestran datos **reales y registrados**. Los totales se mantienen separados por moneda.

**Pronóstico.** Una línea continua muestra tu patrimonio neto real de los últimos seis meses. Las líneas punteadas continúan desde "Hoy" durante
los próximos seis meses, una por cada nivel de confianza en tus ingresos planeados (Conservador, Esperado, Optimista). La tabla
de abajo lista los ingresos planeados, los gastos planeados y el saldo de cada vista por mes. Punteado siempre significa *estimación*.

**Reporte de baldes.** *Fondos disponibles hoy* compara tus baldes, *Dinero que entra y sale, por mes* muestra las asignaciones y
los gastos de los baldes durante seis meses, y la tabla proyecta cada balde 12 meses hacia adelante usando la vista que elijas en
la parte superior (reproduce tu ingreso planeado a través de tus reglas de llenado, como el planificador).

**Reporte de proyectos.** Para cada proyecto (y los artículos sin proyecto): lo que todavía se desea, lo que ya compraste, cuánto
está financiado hoy, y el mes en que se podría comprar todo si sigues el mejor orden del planificador durante los próximos 24
meses, usando la vista elegida de tus ingresos. Los baldes compartidos se cuentan una vez por artículo, así que los porcentajes pueden superponerse.

## Notificaciones

La campana en la barra superior reúne las alertas. Marca una o todas como leídas desde ahí.

- **Umbral de presupuesto / presupuesto superado**: has usado la mayor parte del límite de un balde de gasto, o te pasaste.
- **Meta atrasada**: una meta de ahorro se está atrasando respecto a su fecha objetivo.
- **Pago próximo**: un movimiento recurrente vence en 3 días o menos.
- **Saldo bajo**: el saldo de una cuenta ha bajado de unos tres días de tu gasto habitual, o por debajo de cero.

Se te notifica una vez por cada cruce; una alerta sin leer no se repite.

## Preferencias, perfil y apariencia

- **Configuración → Preferencias**: **moneda** principal, **idioma** (inglés o español) y **zona horaria**. Estos
  controlan cómo se muestran los montos, las fechas y el texto en todas partes. El cambio de idioma se aplica justo después de guardar, sin recargar.
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

**Mi saldo se ve mal.** Los saldos se calculan a partir de tu saldo inicial y cada transacción. Revisa el
saldo inicial de la cuenta y luego busca una transacción faltante o duplicada.

**¿Por qué no puedo eliminar una categoría?** Es una categoría del sistema o ya la usan transacciones. Crea una
nueva y úsala de ahora en adelante.

**¿Por qué no puedo eliminar una cuenta?** Las cuentas con historial se archivan en lugar de eliminarse, para que los reportes antiguos sigan
siendo precisos.

**Una transferencia muestra dos líneas.** Es normal: una por la cuenta de la que salió el dinero y otra por la que llegó.

**¿Por qué veo totales separados para cada moneda?** Jura no convierte entre monedas, así que nunca
las mezcla.

## Límites de esta versión

- Los intereses existen solo en las cuentas, nunca en los baldes, y son solo proyectados; regístralos como una transacción real cuando el
  banco los pague. El monto de llenado de un balde no puede ser distinto solo en el primer mes, y un aumento siempre empieza en el
  ciclo siguiente.
- Jura nunca convierte entre monedas. Cada moneda se planea, se proyecta y se reporta por separado.
- Los gastos planeados reducen tu saldo proyectado de cuenta pero no sacan dinero de los baldes, y el gasto en una
  categoría no se predice cuando el planificador mira hacia adelante.
- Los artículos que comparten un balde se comparan cada uno con el mismo fondo, así que sus barras de "financiado" pueden superponerse.
- Las pantallas, mensajes, etiquetas, exportaciones y PDF siguen tu preferencia de idioma (inglés o español). Los nombres que escribes tú mismo (cuentas, categorías, baldes) se muestran tal como los escribiste.
