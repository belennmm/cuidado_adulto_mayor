"""Regenera el PDF a partir de la evidencia conservada, sin inventar resultados."""
from pathlib import Path
import json
import re
from xml.etree import ElementTree as ET
from xml.sax.saxutils import escape
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak, Preformatted, KeepTogether
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'output/pdf/Tarea_5_Pruebas_Integracion_Regresion_Organizate.pdf'
REPORTS = ROOT / 'tests/automation/reports'
manifest = json.loads((REPORTS / 'demo-manifest.json').read_text(encoding='utf-8'))
full = json.loads((REPORTS / 'full-manifest.json').read_text(encoding='utf-8'))
ci_path = REPORTS / 'github-actions.json'
ci = json.loads(ci_path.read_text(encoding='utf-8-sig')) if ci_path.exists() else None

def junit(name):
    tree = ET.parse(REPORTS / (name + '.xml')).getroot()
    suite = tree if tree.tag == 'testsuite' else tree.find('testsuite')
    return suite.attrib

styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name='BodyES', fontName='Helvetica', fontSize=10, leading=14, spaceAfter=8, textColor=colors.HexColor('#243447')))
styles.add(ParagraphStyle(name='SmallES', parent=styles['BodyES'], fontSize=8.1, leading=11, spaceAfter=4))
styles.add(ParagraphStyle(name='TitleES', fontName='Helvetica-Bold', fontSize=27, leading=33, textColor=colors.HexColor('#12354A'), spaceAfter=18))
styles.add(ParagraphStyle(name='HeadingES', fontName='Helvetica-Bold', fontSize=18, leading=22, textColor=colors.HexColor('#12354A'), spaceAfter=16))
styles.add(ParagraphStyle(name='SubES', fontName='Helvetica-Bold', fontSize=11.5, leading=15, textColor=colors.HexColor('#127B80'), spaceBefore=10, spaceAfter=7))
styles.add(ParagraphStyle(name='CodeES', fontName='Courier', fontSize=8, leading=11, backColor=colors.HexColor('#EDF3F5'), borderPadding=9, spaceAfter=12))
story = []
def p(text, style='BodyES'):
    return Paragraph(text, styles[style])
def body(text): story.append(p(text))
def sub(text): story.append(p(text, 'SubES'))
def code(text): story.append(Preformatted(text, styles['CodeES']))
def page(title):
    if story: story.append(PageBreak())
    story.append(p(title, 'HeadingES'))
def table(headers, rows, widths):
    data = [[p(escape(str(x)), 'SmallES') for x in headers]]
    data += [[p(str(x), 'SmallES') for x in row] for row in rows]
    t = Table(data, colWidths=widths, repeatRows=1, hAlign='LEFT')
    t.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), colors.HexColor('#DCECEF')),
        ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, colors.HexColor('#F4F7F9')]),
        ('VALIGN', (0,0), (-1,-1), 'TOP'), ('BOX',(0,0),(-1,-1),0.5,colors.HexColor('#CCDADD')),
        ('LINEBELOW',(0,0),(-1,0),1,colors.HexColor('#127B80')),
        ('LEFTPADDING',(0,0),(-1,-1),8), ('RIGHTPADDING',(0,0),(-1,-1),8),
        ('TOPPADDING',(0,0),(-1,-1),7), ('BOTTOMPADDING',(0,0),(-1,-1),7),
    ]))
    story.append(t)
    story.append(Spacer(1,10))

story.append(Spacer(1,28))
body('UNIVERSIDAD DEL VALLE DE GUATEMALA<br/>Facultad de Ingeniería<br/>Departamento de Ciencias de la Computación')
story.append(Spacer(1,30))
story.append(p('Tarea 5<br/>Pruebas automatizadas', 'TitleES'))
story.append(p('Integración, regresión y proceso de CI', 'HeadingES'))
body('<b>Organízate - Cuidado del adulto mayor</b><br/>CC3091 - Ingeniería de Software 2<br/>Semestre II, 2026')
story.append(Spacer(1,22))
table(['Integrante', 'Carné'], [
    ['Luis Lee', '241203'], ['Belen Monterroso', '231497'],
    ['Sebastian Lemus', '241155'], ['Luis Hernandez', '241424'],
], [335,160])
body('Fecha de verificación local: 6 de octubre de 2026<br/>Zona horaria: America/Mexico_City')
story.append(Spacer(1,16))
body('<b>Entrega:</b> investigación comparativa, seis pruebas específicas, automatización, reportes originales, experimento reproducible y conclusiones. La grabación del video de 5 a 8 minutos queda pendiente; el guion está preparado.')

page('1. Objetivo y estrategia')
body('La estrategia comprueba interacciones reales entre la API de Organízate, sus validadores, servicios, modelos y persistencia. Además, protege tres comportamientos implementados: impedir horarios invertidos, conservar el rol al actualizar el perfil y bloquear el inicio de sesión de cuidadores pendientes de aprobación.')
sub('Contexto técnico')
body('El repositorio actual declara PHP ^8.3, Laravel ^13.0, Sanctum ^4.3, PHPUnit ^12.0 y Vitest ^5.0.3; el frontend usa HTML, CSS y JavaScript con módulos ES. La aplicación emplea PostgreSQL y las pruebas SQLite en memoria, mediante backend/phpunit.xml. La instalación local anterior conservaba PHP 8.2/Laravel 11, PHPUnit 11.5.55 y Vitest 3.2.7: esos son los runtimes de la evidencia local. El CI instala las versiones de los lockfiles actuales con PHP 8.3 y Node 22.')
sub('Capas y criterios de aceptación')
table(['Capa', 'Verificación y alcance'], [
    ['Integración', 'I01-I03: peticiones HTTP de prueba ejecutadas en Laravel, servicios y modelos reales, migraciones y consultas a SQLite. No se reemplaza la base de datos ni la lógica de negocio con mocks.'],
    ['Regresión', 'R01-R03: reglas conocidas que deben conservarse. Aserciones sobre respuesta HTTP y estado persistido; mismos datos y expectativas antes y después del cambio.'],
    ['Frontend complementario', 'Vitest prueba utilidades y comportamiento DOM. Las respuestas API simuladas de estas pruebas no se presentan como integración frontend-API real.'],
    ['Automatización', 'Proceso Node que ejecuta PHP en procesos separados, conserva JUnit/logs y valida el ciclo aprobado-fallo-aprobado. Workflow por push y pull_request.'],
], [125,370])
body('Cada caso inicia con datos sintéticos y RefreshDatabase. I01 usa inicio de sesión y un token Sanctum real. En I02, I03, R01 y R02 se fija la identidad con Sanctum::actingAs para concentrar el caso en su regla; validadores, controladores, servicios y persistencia continúan siendo reales. R03 prueba credenciales contra la autenticación real.')
sub('Reproducibilidad y límites de interpretación')
body('Las peticiones HTTP se procesan dentro del kernel de Laravel; no cruzan una conexión de red. SQLite es persistencia real durante la prueba, pero no prueba particularidades de PostgreSQL. Los seis casos nuevos se suman a la suite existente; el total del backend incluye pruebas de distintos tipos y no se clasifica íntegramente como integración.')

page('2. Comparación de frameworks')
body('Se compararon tres alternativas compatibles con las tecnologías del proyecto. La selección es una valoración técnica del equipo basada en la documentación oficial [1]-[5] y la ejecución local.')
table(['Criterio', 'PHPUnit + Laravel [1,2]', 'Vitest + jsdom [3]', 'Playwright [4,5]'], [
    ['Compatibilidad', 'PHP/Laravel y Eloquent; instalado.', 'JS y módulos ES del frontend; instalado.', 'Frontend web en navegador; también permite pruebas API.'],
    ['Configuración', 'Composer, phpunit.xml, factories y RefreshDatabase.', 'npm, vitest.config.js, entorno jsdom y setup.', 'Paquete npm, navegadores, servidor y datos de prueba.'],
    ['Tipos de pruebas', 'Unitarias y Feature; integración y regresión HTTP/persistencia.', 'Unitarias, DOM y regresión; API real requiere preparación adicional.', 'Funcionales E2E, API y regresión visual.'],
    ['Flujo de desarrollo', 'CLI PHP; fácil ejecución en push y PR.', 'CLI/watch; ejecución con npm en CI.', 'CLI; servidor y navegadores en CI.'],
    ['Reportes', 'JUnit XML y salida TestDox.', 'Consola y JUnit XML.', 'HTML, JUnit, trazas y capturas.'],
    ['Documentación', 'Manual versionado y guía HTTP de Laravel.', 'Guía oficial, configuración y reporters.', 'Guías oficiales de pruebas, CI y reportes.'],
    ['Ventajas', 'Aserciones HTTP y de BD; reutiliza el entorno del backend.', 'Aprovecha las 49 suites del frontend con ejecución sencilla.', 'Comprueba interacción en navegadores reales.'],
    ['Limitaciones', 'No demuestra red ni interfaz de navegador; BD elegida afecta alcance.', 'jsdom y API simulada no prueban integración real con servidor.', 'Mayor preparación y dependencia de servidor, datos y selectores.'],
], [91,135,135,134])
sub('Selección y justificación')
body('<b>PHPUnit con Laravel</b> es la herramienta principal: sus casos ejercitan rutas, servicios y SQLite sin introducir otro framework PHP. <b>Vitest</b> complementa con la suite JS existente. <b>Playwright</b> se reserva para ampliar E2E: aporta navegador real, pero no es necesario para las integraciones API-persistencia elegidas. La decisión responde a compatibilidad, evidencia y costo de preparación, no al uso de una herramienta en clase.')

page('3. Comparación de integración continua')
body('Las tres alternativas pueden ejecutar Composer, PHP, Node y pruebas. Los detalles siguientes proceden de las guías oficiales [6]-[8]; la preferencia por Actions se fundamenta también en que el remoto del proyecto está alojado en GitHub.')
table(['Criterio', 'GitHub Actions [6]', 'GitLab CI/CD [7]', 'Jenkins [8]'], [
    ['Compatibilidad', 'Runners para comandos PHP/Node; conexión directa con el repositorio GitHub.', 'Runners e imágenes capaces de ejecutar PHP/Node.', 'Agentes configurables con PHP/Node.'],
    ['Configuración', 'YAML en .github/workflows y pasos de preparación.', '.gitlab-ci.yml y runner; requiere integración con GitLab.', 'Jenkinsfile, servidor Jenkins, agentes y plugins necesarios.'],
    ['Pruebas soportadas', 'Ejecuta cualquier suite CLI; no define las aserciones.', 'Ejecuta suites CLI en jobs y stages.', 'Ejecuta suites CLI mediante etapas del pipeline.'],
    ['Flujo de desarrollo', 'Eventos push, pull_request y disparo manual.', 'Pipelines por commits, merge requests y ejecución manual.', 'Pipeline conectado al SCM y disparadores configurables.'],
    ['Resultados', 'Estado de jobs, logs, resumen y archivos como artefactos.', 'Estado de pipelines/jobs y artefactos; reportes de pruebas configurables.', 'Historial y logs; publicación JUnit con plugins.'],
    ['Documentación', 'Guías oficiales de eventos, runners y workflows.', 'Guías de YAML, runners y pipelines.', 'Manual de Pipeline y Jenkinsfile.'],
    ['Ventajas', 'Menor preparación para este remoto; evidencia junto al código.', 'Configuración declarativa y control mediante runners.', 'Control de infraestructura y amplia personalización.'],
    ['Limitaciones', 'Depende de runners, permisos y disponibilidad de GitHub.', 'Añade configuración o migración para el flujo actual en GitHub.', 'El equipo debe mantener servidor, agentes y plugins.'],
], [91,135,135,134])
sub('Combinación elegida')
body('<b>PHPUnit + Laravel, Vitest y GitHub Actions.</b> Actions agrega automatización al repositorio existente, prepara ambos runtimes y conserva archivos de evidencia. GitLab CI sería razonable si el equipo trasladara su flujo a GitLab. Jenkins sería útil con infraestructura propia y necesidades de control que aquí no justifican su mantenimiento adicional.')

page('4. Pruebas de integración implementadas')
body('Código nuevo: backend/tests/Feature/Tarea5/IntegrationTest.php. Todos los casos usan RefreshDatabase y comprueban una respuesta de la API y datos persistidos, sin mock de servicios ni base de datos.')
sub('I01. Login, token y perfil')
body('<b>Componentes:</b> ruta POST /api/login, AuthController/AuthService, hash, User, tabla personal_access_tokens, Sanctum y GET /api/me.<br/><b>Condición:</b> el token emitido para credenciales válidas existe en la base de datos y permite leer el perfil correcto.<br/><b>Datos:</b> profesional aprobado, integracion@example.com y contraseña secret123 almacenada mediante hash.<br/><b>Resultado:</b> login 200, token de tipo string, fila vinculada al usuario y perfil 200 con el mismo id. Cinco aserciones. La autenticación del perfil usa el token emitido, no actingAs.')
sub('I02. Adulto mayor, medicamento e ingreso de inventario')
body('<b>Componentes:</b> POST y GET /api/admin/older-adults, OlderAdultRequest, OlderAdultController/OlderAdultService, Eloquent y tablas older_adults, medications, older_adult_medications y medication_acquisitions.<br/><b>Condición:</b> crear un adulto con medicación conserva sus relaciones y el ingreso inicial de inventario; una lectura posterior devuelve el adulto creado.<br/><b>Datos:</b> administrador, familiar y profesional aprobados; Rosa Integracion, 82 años, habitación T5-101; Losartan T5, 1 tableta a las 08:00, lunes, cantidad 7, mínimo 2.<br/><b>Resultado:</b> creación 201; medicamento en JSON; filas del adulto, medicamento, asignación y adquisición con cantidad 7; consulta posterior 200. Ocho aserciones.')
sub('I03. Nota de seguimiento y relaciones')
body('<b>Componentes:</b> POST /api/professional/routine-notes, validación, ProfessionalRoutineNoteController, RoutineNoteService, comprobación de asignación, modelos y tabla routine_notes.<br/><b>Condición:</b> la nota creada vincula al profesional autenticado y al adulto asignado.<br/><b>Datos:</b> profesional aprobado; Rosa Nota, habitación T5-102, asignada a ese profesional; contenido Seguimiento de integracion T5.<br/><b>Resultado:</b> HTTP 201, ids correctos en JSON y fila con el contenido y ambas claves en la base de datos. Cuatro aserciones.')
sub('Qué aporta la integración real')
body('Un servicio que omita guardar la adquisición, una relación con id incorrecto, un contrato JSON modificado o un token que no se persista puede hacer fallar estos casos, aunque los métodos aislados continúen funcionando. La prueba atraviesa más de un componente real y verifica el efecto observable en la persistencia.')

page('5. Pruebas de regresión implementadas')
body('Código nuevo: backend/tests/Feature/Tarea5/RegressionTest.php. Las funcionalidades ya existían y estaban cubiertas por AuthenticationTest, ProfileRoleProtectionTest y CaregiverScheduleEndTimeValidationTest. Los casos específicos fijan una línea base de la tarea y protegen el mismo comportamiento en ejecuciones posteriores.')
sub('R01. Rechazo de horarios invertidos')
body('<b>Comportamiento protegido:</b> end_time debe ser posterior a start_time y un horario inválido no debe persistirse.<br/><b>Componentes:</b> POST /api/schedules, SaveCaregiverScheduleRequest, controlador, CaregiverScheduleService y tabla caregiver_schedules.<br/><b>Datos:</b> profesional aprobado, día 3, inicio 16:00 y fin 08:00.<br/><b>Resultado esperado:</b> 422, error de validación de end_time y cero horarios. Cuatro aserciones.<br/><b>Cambio que provoca regresión:</b> retirar after:start_time del validador. Este es el cambio deliberado demostrado experimentalmente.')
sub('R02. Protección del rol al actualizar perfil')
body('<b>Comportamiento protegido:</b> un profesional puede actualizar su nombre, pero no convertirse en administrador mediante PUT /api/me.<br/><b>Componentes:</b> AuthRequest, AuthController/AuthService, User y tabla users.<br/><b>Datos:</b> profesional aprobado; nombre Profesional T5; email actual; campo inyectado role=admin.<br/><b>Resultado esperado:</b> 200 para la modificación permitida; JSON y base de datos mantienen role=profesional y guardan el nombre. Tres aserciones.<br/><b>Cambio que podría provocar regresión:</b> aceptar role en los campos de actualización o pasar todo el payload a User::update.')
sub('R03. Bloqueo de cuidadores pendientes')
body('<b>Comportamiento protegido:</b> tener credenciales correctas no basta para iniciar sesión si el cuidador aún no está aprobado.<br/><b>Componentes:</b> POST /api/login, AuthService, User, hash y tokens Sanctum.<br/><b>Datos:</b> profesional con is_approved=false y contraseña secret123 válida.<br/><b>Resultado esperado:</b> HTTP 403 y cero filas en personal_access_tokens. Dos aserciones.<br/><b>Cambio que podría provocar regresión:</b> eliminar la comprobación de aprobación, invertir la condición o crear un token antes de rechazar al usuario.')
sub('Criterio de detección')
body('Una respuesta correcta sin comprobar datos podría ocultar una escritura indebida. Por ello los casos también verifican ausencia de horarios y tokens o preservación del rol. Se mantienen las expectativas originales en las tres fases; no se altera la prueba para lograr un resultado verde.')

page('6. Experimento: aprobado, fallo y recuperación')
body('Ejecución local real del 6 de octubre de 2026, registrada por tests/automation/run.mjs. Cada fase inicia un proceso PHP separado y usa los mismos seis casos. Los reportes JUnit y registros originales se conservan en tests/automation/reports/.')
rows = []
for result in manifest['results']:
    a = junit(result['phase'])
    rows.append([result['phase'], a['tests'], a.get('assertions','-'), a['failures'], result['exit_code'], f"{result['elapsed_seconds']:.3f} s"])
table(['Fase', 'Pruebas', 'Aserciones', 'Fallos', 'Salida', 'Tiempo total'], rows, [104,65,82,62,62,120])
sub('Cambio deliberado en el componente real')
code("Original:\n'end_time' => ['required', 'date_format:H:i', 'after:start_time'],\n\nMutacion:\n'end_time' => ['required', 'date_format:H:i'],")
body('El script copia la clase SaveCaregiverScheduleRequest y retira únicamente la regla indicada. PHP carga esa copia mediante auto_prepend_file antes de que Laravel resuelva el validador. Se ejecuta así la API con el componente modificado, incluyendo su escritura real en SQLite. El código original no se sobrescribe.')
sub('Evidencia de la detección')
code('...F..  6 / 6\nExpected response status code [422] but received 201.\nTests: 6, Assertions: 23, Failures: 1.')
body('R01 falla porque el sistema crea el horario invertido; los otros cinco casos aprueban. El runner exige código 1, exactamente un fallo de aserción, cero errores y el mensaje 201 frente a 422. Un fallo de infraestructura no se aceptaría como evidencia de regresión.')
sub('Corrección y comprobación')
body('La fase corrected deja de cargar la copia modificada y recupera la regla original after:start_time. Las seis pruebas vuelven a aprobar con 26 aserciones. El manifiesto registra source_restored=true y experiment_success=true; los hashes SHA-256 del archivo original antes y después coinciden.')
code('node tests/automation/run.mjs baseline\nnode tests/automation/run.mjs regression  # salida 1 esperada\nnode tests/automation/run.mjs corrected\nnode tests/automation/run.mjs demo        # valida todo el ciclo')

page('7. Proceso de integración continua')
body('Configuración: .github/workflows/tarea5-tests.yml. Eventos: push, pull_request y workflow_dispatch. Dos jobs en Ubuntu preparan PHP 8.3/Node 22 y ejecutan los comandos del repositorio. El workflow tiene permiso contents:read y no despliega la aplicación.')
table(['Etapa', 'Acción y evidencia'], [
    ['Preparación backend', 'Checkout; PHP con mbstring, PDO SQLite, DOM y XML; Node; composer install desde composer.lock.'],
    ['Integración/regresión', 'node tests/automation/run.mjs demo: verifica línea base, detecta el fallo deliberado y verifica la recuperación. Un fallo inesperado devuelve código no cero.'],
    ['Suite backend', 'node tests/automation/run.mjs full después del experimento exitoso: revisa también la suite existente.'],
    ['Frontend', 'npm ci desde package-lock.json; npm test con reporters de consola y JUnit.'],
    ['Conservación', 'upload-artifact con if:always(); JUnit, logs, manifiestos y diff del backend, más reporte frontend. Retención: 30 días.'],
    ['Resultado visible', 'Estado verde/rojo del job, consola y resumen de ejecución. La fase manual regression produce un job rojo deliberadamente.'],
], [120,375])
body('Los reportes del CI se generan en ci-reports/, separado de la evidencia local versionada, para evitar mezclar resultados históricos con una ejecución nueva. Las pruebas utilizan la base SQLite en memoria; las migraciones se preparan mediante RefreshDatabase.')
sub('Interpretación del experimento en CI')
body('En demo, la fase regression falla por diseño y el orquestador valida esa detección antes de continuar. El job completo termina verde solo si aprueban baseline, la comprobación del fallo esperado, corrected y la suite completa. En workflow_dispatch, elegir regression por separado hace que su código 1 se propague y el job aparezca rojo. Los artefactos se guardan en ambos casos.')
sub('Estado de la evidencia remota')
if ci:
    body('Ejecuciones reales por push, registradas en github-actions.json. La fase de regresión se seleccionó temporalmente en el workflow de la rama de entrega y después se recuperó demo; el workflow final conserva demo como valor predeterminado. Cada ejecución descargada incluye estados de jobs, logs y artefactos originales.')
    ci_rows = []
    for run in ci.get('runs', []):
        ci_rows.append([run['phase'], run['conclusion'], f"<link href='{escape(run['html_url'])}' color='#127B80'>Ejecución {run['id']}</link>"])
    table(['Fase remota', 'Estado', 'Enlace verificable'], ci_rows, [140,85,270])
    body('La ejecución roja de ci-regression falla en R01: esperaba HTTP 422 y recibió 201; los otros cinco casos aprueban y no hay errores de infraestructura. Los jobs verdes ejecutan el experimento completo, 186 pruebas de backend y 123 de frontend. La regresión deliberada queda retirada del comportamiento predeterminado.')
else:
    body('El workflow está implementado y los mismos comandos fueron verificados localmente. Al generar esta versión todavía no se ha conservado una ejecución remota de GitHub Actions. La evidencia local no se presenta como ejecución del servicio remoto. La publicación de la rama activará el evento push.')

page('8. Resultados, esfuerzo y configuración')
baseline = junit('baseline'); backend = junit('full'); frontend = junit('frontend')
table(['Ejecución local', 'Pruebas', 'Resultado', 'Tiempo'], [
    ['Casos específicos Tarea 5', '6', '0 fallos; 26 aserciones', f"{manifest['results'][0]['elapsed_seconds']:.3f} s (proceso)"],
    ['Mutación controlada', '6', '1 fallo R01; 5 aprobadas', f"{manifest['results'][1]['elapsed_seconds']:.3f} s (proceso)"],
    ['Recuperación', '6', '0 fallos; 26 aserciones', f"{manifest['results'][2]['elapsed_seconds']:.3f} s (proceso)"],
    ['Backend completo', backend['tests'], f"0 fallos; {backend.get('assertions','-')} aserciones", f"{full['results'][0]['elapsed_seconds']:.3f} s (proceso)"],
    ['Frontend completo', frontend['tests'], '49 archivos aprobados; 0 fallos', '35.34 s (Vitest)'],
], [160,55,160,120])
body('El backend completo incluye los seis casos nuevos; no deben sumarse nuevamente a su total. Backend y frontend ofrecen 309 pruebas distintas aprobadas en la verificación amplia. Los tiempos del runner incluyen arranque PHP y escritura de reportes; el tiempo Vitest es el informado por su consola. Son mediciones de esta máquina, no un SLA ni una medición de GitHub.')
sub('Tiempo y esfuerzo')
body('El experimento de tres fases consumió aproximadamente 8.29 segundos de procesos medidos. La suite completa del backend requirió 64.50 segundos y Vitest 35.34 segundos; estos últimos procesos se ejecutaron de forma concurrente. El esfuerzo principal consistió en elegir fronteras reales, preparar datos y aserciones, aislar la mutación, manejar códigos de salida y producir evidencia legible. No se dispone de un registro de horas-persona del equipo, por lo que no se declara una cifra ficticia.')
sub('Problemas encontrados y respuesta')
body('<b>Ruta del ejecutable:</b> la primera invocación de PHPUnit omitía el último segmento; se fijó vendor/phpunit/phpunit/phpunit y cwd explícito.<br/><b>Estado cambiante del proyecto:</b> una ejecución exploratoria coincidió con cambios del backend y mostró errores de hash/CORS. La repetición sobre el estado actualizado terminó con 186 pruebas aprobadas; no se atribuyen esas correcciones a esta tarea.<br/><b>Dependencias del CI:</b> la primera ejecución remota instaló PHPUnit 12.5.38 y detectó que los lockfiles exigían PHP &gt;=8.3. Se corrigió setup-php de 8.2 a 8.3, sin ignorar requisitos de plataforma.<br/><b>Mutación y reportes:</b> se verificó el fallo exacto, se conservaron hashes del original y se separó evidencia local de CI.')
sub('Utilidad observada')
body('PHPUnit dio una explicación precisa del contrato roto y comprobó persistencia. Vitest validó la suite JS existente. El runner produjo registros y manifiestos reproducibles, y Actions integra su ejecución con cambios del repositorio. El fallo deliberado evidencia sensibilidad a una regresión concreta; no prueba que toda regresión posible esté cubierta.')

page('9. Alcance y conclusiones')
table(['Puede detectar', 'Fuera del alcance actual'], [
    ['Cambio de estados HTTP o claves JSON en los flujos probados.', 'Flujos y combinaciones de datos sin casos automatizados.'],
    ['Ausencia de tokens, notas, medicamentos, relaciones o adquisiciones esperadas.', 'Problemas específicos de PostgreSQL, concurrencia o transacciones bajo carga.'],
    ['Pérdida de validación temporal, aprobación o protección del rol.', 'Auditoría exhaustiva de seguridad, ataques nuevos y toda configuración productiva.'],
    ['Regresiones en utilidades y DOM cubiertas por Vitest.', 'Renderizado real, CSS, accesibilidad completa y compatibilidad de navegadores.'],
    ['Fallos de instalación y ejecución en el pipeline.', 'Disponibilidad de servicios externos, latencia de red y desempeño productivo.'],
], [247,248])
sub('Conclusiones sustentadas')
body('<b>1.</b> Las tres integraciones aprobadas comprueban efectos entre API y persistencia: token-perfil, adulto-medicación-inventario y nota-asignación. Su evidencia va más allá de verificar un método aislado.<br/><b>2.</b> Los tres casos de regresión fijan comportamientos con consecuencias operativas y de acceso. El experimento de horarios demuestra que R01 detecta la eliminación de la regla y que la corrección recupera el comportamiento.<br/><b>3.</b> Se mantendrían PHPUnit/Laravel y Vitest: ofrecen reportes utilizables y aprobaron sus suites tanto localmente como con las dependencias actuales del CI. Se mantendría Actions por su ajuste a GitHub; las ejecuciones remotas verde-roja-verde confirman la automatización y la conservación de evidencia.<br/><b>4.</b> SQLite permite aislamiento y rapidez, pero no sustituye validar PostgreSQL. Una siguiente ampliación útil es un job contra PostgreSQL y pruebas Playwright de flujos críticos en navegador.<br/><b>5.</b> El experimento controlado proporciona una evidencia concreta de detección. La confianza depende de mantener datos, contratos y escenarios representativos al evolucionar el sistema.')
sub('Material de entrega y grabación')
body('El PDF documenta investigación, elección, casos, evidencia y conclusiones. El repositorio contiene las pruebas, el runner, el workflow y los reportes. docs/TAREA5_GUION_VIDEO.md prepara una demostración de aproximadamente 7 minutos con responsables sugeridos, comandos, resultados y explicación del CI. La grabación y su enlace se agregarán al realizar el video.')

page('10. Trazabilidad y referencias')
table(['Requisito / evaluación', 'Evidencia'], [
    ['Investigación de frameworks y CI (15 puntos)', 'Secciones 2 y 3: tres alternativas de cada categoría y criterios mínimos.'],
    ['Justificación (15 puntos)', 'Selección en secciones 2 y 3; contexto del proyecto en sección 1.'],
    ['Diseño pertinente (20 puntos)', 'I01-I03 y R01-R03: condición, componentes, datos, resultado y riesgos de cambio.'],
    ['Implementación (20 puntos)', 'Dos clases nuevas de Tarea5; JUnit y logs de baseline/corrected.'],
    ['Proceso automatizado (15 puntos)', 'Workflow push/PR, instalación, dos jobs y artefactos; sección 7.'],
    ['Detección y recuperación (10 puntos)', 'Regla retirada, R01 en fallo, recuperación y manifiesto; sección 6.'],
    ['Análisis y conclusiones (5 puntos)', 'Resultados, esfuerzo, problemas y límites; secciones 8 y 9.'],
    ['Video de 5 a 8 minutos', 'Guion preparado; grabación pendiente.'],
], [214,281])
body('Fuentes primarias consultadas el 6 de octubre de 2026. Las comparaciones se parafrasean; no se copian artículos ni se atribuyen resultados de ejecución a la documentación.')
refs = [
    ('1', 'Laravel 11 - HTTP Tests', 'https://laravel.com/docs/11.x/http-tests'),
    ('2', 'PHPUnit 11.5 - Command-Line Test Runner', 'https://docs.phpunit.de/en/11.5/textui.html'),
    ('3', 'Vitest - Reporters', 'https://vitest.dev/guide/reporters'),
    ('4', 'Playwright - Continuous Integration', 'https://playwright.dev/docs/ci'),
    ('5', 'Playwright - Test Reporters', 'https://playwright.dev/docs/test-reporters'),
    ('6', 'GitHub - Understanding GitHub Actions', 'https://docs.github.com/en/actions/get-started/understand-github-actions'),
    ('7', 'GitLab - Get started with CI/CD', 'https://docs.gitlab.com/ci/'),
    ('8', 'Jenkins - Pipeline', 'https://www.jenkins.io/doc/book/pipeline/'),
    ('9', 'Laravel 13 - HTTP Tests (versión del CI)', 'https://laravel.com/docs/13.x/http-tests'),
    ('10', 'PHPUnit 12.5 - CLI (versión del CI)', 'https://docs.phpunit.de/en/12.5/textui.html'),
]
for n, title, url in refs:
    story.append(p(f'[{n}] {title}. <link href="{url}" color="#127B80">{url}</link>', 'SmallES'))
body('Fuente de requisitos: Tarea 5. Pruebas automatizadas. Integración, Regresión, Funcionales. 2026.pdf, Universidad del Valle de Guatemala, CC3091, 2 páginas. Fuente experimental: archivos del proyecto y reportes generados por PHPUnit/Vitest incluidos con esta entrega.')

def footer(canvas, doc):
    canvas.saveState()
    canvas.setStrokeColor(colors.HexColor('#CCDADD'))
    canvas.line(50,43,A4[0]-50,43)
    canvas.setFont('Helvetica',8)
    canvas.setFillColor(colors.HexColor('#516578'))
    canvas.drawString(50,30,'Organízate | CC3091 | Tarea 5 | Integración y regresión')
    canvas.drawRightString(A4[0]-50,30,f'{doc.page}')
    canvas.restoreState()

OUT.parent.mkdir(parents=True, exist_ok=True)
SimpleDocTemplate(str(OUT), pagesize=A4, rightMargin=50, leftMargin=50, topMargin=48,
                  bottomMargin=58, title='Tarea 5 - Pruebas automatizadas - Organízate',
                  author='Luis Lee; Belen Monterroso; Sebastian Lemus; Luis Hernandez').build(story,onFirstPage=footer,onLaterPages=footer)
print(OUT)
