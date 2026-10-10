# Cierre A03: SCRUM-836 a SCRUM-842

## Alcance y reutilización

Se completa el bloque pendiente de SCRUM-823. SCRUM-832 a SCRUM-835 ya están implementadas y se conservan; no se repiten cambios de URL, CSP, errores ni sus pruebas. No se realizan acciones de Git. Los cambios quedan agrupados para que el usuario prepare un único commit.

## SCRUM-836: normalización antes de validar

`StrictFormRequest::prepareForValidation()` aplica UTF-8, normalización Unicode NFC y recorte de espacios Unicode a valores textuales antes de construir el validador. Recorre estructuras anidadas con límite explícito y rechaza controles no textuales. La clase `Normalizer` está disponible mediante el polyfill de Symfony instalado por las dependencias existentes; no requiere habilitar intl ni instalar paquetes nuevos.

Las contraseñas son opacas: no se recortan, normalizan ni decodifican. Se añaden `new_password` y su confirmación a las excepciones de TrimStrings, además de las que Laravel ya trae para password/current_password.

JSON se decodifica una vez según su formato y el framework decodifica los formularios/query según HTTP. No se aplican decodificaciones HTML/URL adicionales a los valores: `%253Cscript%253E` permanece texto. No se eliminan acentos, no se hace NFKC ni se convierten nombres de campos desconocidos en claves permitidas. Las listas permitidas existentes siguen decidiendo si el resultado es válido.

## SCRUM-837: archivos

La aplicación no tiene endpoints de carga ni campos de archivo declarados. Se rechaza cualquier archivo en `prepareForValidation()`, incluso si ocupa un campo conocido y declara nombre y MIME aparentemente válidos. Por ello ninguna decisión de aceptación depende de extensión, nombre o MIME proporcionado por el cliente. Una prueba HTTP comprueba rechazo y ausencia de escritura.

Se omite crear un validador de tipos de archivo sin consumidor. Si se introduce una función de carga, necesitará su propio contrato de tamaño, nombres generados por servidor y comprobación de contenido/MIME real.

## SCRUM-838: CSV

No existen exportaciones CSV ni generación de archivos de hoja de cálculo en el código de la aplicación. No se añade una exportación ni un escapador sin uso. El análisis estático bloquea la introducción de `fputcsv` hasta que se revise el nuevo flujo y su defensa contra fórmulas. El alcance actual se cierra como no aplicable, no como una exportación implementada.

## SCRUM-839: correo, encabezados y redirecciones

No existe envío de correo. Los encabezados se asignan mediante las APIs del framework con nombres y valores constantes o configuraciones del servidor; no se construyen a partir de textos del usuario. Las redirecciones de navegación pasan por la validación de mismo origen de SCRUM-832, y las peticiones API no siguen redirecciones HTTP. Los otros destinos del frontend son rutas constantes, listas permitidas o componentes codificados.

El control estático detecta funciones globales `mail` y `header` y exige revisar su introducción. Distingue esas funciones de métodos seguros como `$request->header()` y `$response->header()`.

## SCRUM-840: codificación de salida

Se reutilizan las defensas y pruebas de SCRUM-830/831/832/835. Texto y valores de formulario usan nodos DOM, `textContent` y `.value`; las plantillas restantes escapan texto y atributos mediante `escapeHtml`. URLs requieren validación de esquema/origen y codificación de componentes, nunca solo escape HTML. Las clases dinámicas proceden de listas permitidas y los valores CSS numéricos de conversiones acotadas. No hay interpolación de entrada en scripts ni handlers HTML.

Se omite sustituir plantillas estáticas o repetir pruebas ya existentes. El inventario y los casos de XSS se conservan en `CONSULTAS_DOM_CONTEXTO_A03.md` y `URL_CSP_ERRORES_PRUEBAS_A03.md`.

## SCRUM-841: JSON inequívoco

`App\Support\StrictJson` valida JSON antes de que las reglas de negocio puedan consumirlo:

- Rechaza claves duplicadas en cada objeto, también cuando una clave usa escapes Unicode equivalentes.
- Distingue puntuación estructural de comillas, llaves y dos puntos incluidos dentro de strings.
- Limita el anidamiento a 16 contenedores.
- Rechaza controles no permitidos presentes en el JSON original, antes de que TrimStrings pudiera ocultarlos.
- Requiere un objeto en la raíz. Conserva únicamente `[]` vacío como representación compatible de solicitudes sin datos de clientes y pruebas existentes; los campos obligatorios siguen impidiendo usarlo en operaciones con datos.
- Rechaza objetos donde el contrato exige listas, incluso `{}` o claves numéricas que PHP convertiría en arrays.

Las reglas existentes siguen rechazando tipos, campos desconocidos y arrays excesivos. `EnforceRequestSize` comprueba también bytes reales del cuerpo, sin confiar exclusivamente en Content-Length.

## SCRUM-842: análisis estático

`php tests/security/php-sast.php` tokeniza PHP sin ejecutar el código de aplicación. Revisa app, rutas y base de datos; detecta SQL raw dinámico/concatenado/interpolado, ejecución de comandos y código dinámico, deserialización y funciones de correo/encabezados/CSV sin revisión. No confunde comentarios ni el array de bindings con SQL concatenado. Sus pruebas incluyen muestras inseguras y consultas parametrizadas válidas.

`.github/workflows/injection-static-analysis.yml` incorpora ese control y reglas Semgrep de `tests/security/semgrep.yml` para concatenación SQL, comandos y flujos de datos del navegador hacia HTML o eval. El workflow se ejecutará en push, pull request o manualmente cuando el usuario gestione Git. No se ejecutó ninguna acción remota.

El guard local no pretende ser un analizador completo de flujo de datos. Semgrep complementa ese alcance en CI; no se ejecutó localmente porque no está instalado. La configuración añadida no equivale a afirmar que una ejecución remota ya pasó.

## Verificación

Se agregan 27 pruebas entre `CanonicalInputTest` y `StrictJsonAndStaticAnalysisTest`: Unicode, contraseñas opacas, archivos, tamaño real, duplicados, escapes, listas, profundidad y muestras del analizador. Los casos nuevos se verifican junto con la suite completa. El control estático de PHP pasa sobre el código actual. Pint verifica los siete archivos PHP de este bloque.

Resultado final: **445 pruebas y 6997 aserciones aprobadas**. El guard de PHP y Pint pasan; la sintaxis YAML del workflow y de las reglas se valida con Symfony Yaml.

La suite completa del backend se registra en `backend/storage/logs/a03-final.txt` y `a03-final.xml`. No cambia el frontend; se conserva su última ejecución completa de 168 pruebas aprobadas. Entorno instalado: PHP 8.2.12, Laravel 11.51.0 y PHPUnit 11.5.55; base de pruebas SQLite en memoria. No se certifican aquí las versiones superiores declaradas por los manifiestos.
