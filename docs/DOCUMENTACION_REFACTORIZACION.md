# Documentación de refactorización

## 1. Información general

**Proyecto:** Organízate - Cuidado Adulto Mayor  
**Branch de trabajo:** `refactor`  
**Objetivo:** reducir la deuda técnica sin modificar el comportamiento funcional del sistema, facilitando el mantenimiento, las pruebas, la reutilización y la incorporación de nuevas funcionalidades.

La refactorización se realizó de forma incremental. Cada cambio se aisló por responsabilidad y se comprobó mediante pruebas automatizadas para disminuir el riesgo de regresiones.

## 2. Deuda técnica identificada

Antes de la refactorización se identificaron los siguientes problemas:

1. Archivos JavaScript y CSS demasiado grandes, con varias responsabilidades mezcladas.
2. Lógica de presentación, eventos, peticiones HTTP y reglas de negocio reunidas en un mismo archivo.
3. Código duplicado en formularios de usuarios y adultos mayores.
4. Utilidades y mensajes de formularios repetidos en distintas pantallas.
5. Solicitudes HTTP de incidentes y movilidad implementadas de forma diferente.
6. Controladores Laravel extensos, responsables simultáneamente de validar, autorizar, consultar, transformar y responder.
7. Validaciones repetidas directamente dentro de los controladores.
8. Respuestas JSON construidas manualmente y con estructuras difíciles de mantener.
9. Reglas de autorización distribuidas entre distintos métodos.
10. Módulos extraídos sin pruebas unitarias específicas.
11. Archivos temporales y artefactos generados almacenados dentro del repositorio.
12. Hojas de estilo monolíticas difíciles de localizar, revisar y reutilizar.

## 3. Técnicas de refactorización utilizadas

### Extraer módulo

Se separaron responsabilidades independientes en archivos especializados. Por ejemplo, los módulos de rutinas, calendario, turnos, notificaciones y estadísticas se dividieron en servicios, vistas, eventos, formularios y acciones.

### Extraer método y servicio

La lógica de negocio que estaba dentro de los controladores se trasladó a clases de servicio. Los controladores quedaron enfocados en recibir la solicitud, delegar la operación y devolver la respuesta.

### Separación de responsabilidades

Se aplicó una organización similar al principio de responsabilidad única:

- **Service:** acceso a datos y reglas de negocio.
- **Form Request:** validación y normalización de entradas.
- **Resource:** transformación uniforme de respuestas JSON.
- **Policy:** autorización sobre recursos.
- **View frontend:** renderizado y actualización del DOM.
- **Módulo HTTP:** comunicación con la API.
- **Módulo de eventos:** interacción del usuario.

### Eliminar duplicación

Se centralizaron formularios, mensajes y utilidades compartidas para evitar mantener varias implementaciones de la misma funcionalidad.

### Encapsular peticiones HTTP

Las solicitudes de incidentes y movilidad se trasladaron a servicios frontend específicos, dejando a las vistas y controladores de página independientes del detalle de `fetch`.

### Reemplazar respuestas manuales

Se incorporaron API Resources para mantener respuestas JSON consistentes y evitar repetir transformaciones en los controladores.

### Reemplazar condicionales de autorización

Las reglas relacionadas con el acceso a rutinas se trasladaron a Policies, centralizando las decisiones de autorización.

### División de archivos CSS

Las hojas de estilo grandes se dividieron en archivos ordenados dentro de carpetas `.parts`. El archivo CSS original se conservó como punto de entrada mediante `@import`, por lo que no fue necesario cambiar los enlaces de las páginas HTML.

## 4. Trabajos realizados

### 4.1 Frontend

- Centralización de utilidades y mensajes de formularios.
- Unificación de los formularios administrativos de usuarios.
- Unificación de los formularios de adultos mayores.
- Separación de rutinas profesionales en formulario, servicio, vista, eventos y acciones.
- Separación de la vista de rutinas administrativas.
- División del sistema de ventanas emergentes.
- Separación de los interceptores de la animación de carga.
- División del sistema de notificaciones en núcleo, audio y vista.
- Separación del calendario de turnos en lógica de fechas y vista.
- Separación de las vistas de turnos administrativos.
- Separación de estadísticas de medicamentos en servicio, vista y diálogos.
- Separación de incidentes y movilidad en servicios HTTP y vistas.
- Incorporación de pruebas específicas para los nuevos módulos.
- Eliminación de archivos temporales del repositorio.

### 4.2 CSS

Se dividieron 12 hojas de estilo grandes en 34 módulos. Entre ellas se encuentran los estilos globales, componentes, autenticación, dashboard, estadísticas de medicamentos, movilidad, incidentes, calendario y páginas de cuidadores.

Después de la división:

- No quedan archivos CSS superiores a 300 líneas.
- El módulo generado más grande tiene menos de 280 líneas.
- Se conservaron el orden y la cascada de las reglas.
- No fue necesario modificar los HTML para cargar los nuevos módulos.

### 4.3 Backend

- Reducción de controladores extensos.
- Creación de servicios para dashboard, usuarios, autenticación, adultos mayores, cuidado familiar y profesional, horarios, rutinas, incidentes, recordatorios, movilidad, medicamentos y vacaciones.
- Extracción de validaciones a Form Requests.
- Creación de API Resources para respuestas uniformes.
- Centralización de autorización de rutinas mediante Policy.
- Protección de actualizaciones de perfil mediante transacciones.
- Separación de la lógica de inventario, administración y estadísticas de medicamentos.
- Separación de la lógica de solicitudes de vacaciones.
- Simplificación de los controladores de incidentes y recordatorios.

La arquitectura resultante contiene 17 controladores, 18 servicios, 20 Form Requests y 12 API Resources con responsabilidades diferenciadas.

## 5. Plan de refactorización aplicado

| Fase | Trabajo | Estado |
|---|---|---|
| 1 | Identificar archivos grandes, duplicación y responsabilidades mezcladas | Completado |
| 2 | Proteger el comportamiento con pruebas | Completado |
| 3 | Centralizar utilidades y formularios duplicados | Completado |
| 4 | Dividir módulos JavaScript grandes | Completado |
| 5 | Reparar y encapsular solicitudes HTTP | Completado |
| 6 | Reducir controladores del backend | Completado |
| 7 | Extraer servicios, validaciones, recursos y políticas | Completado |
| 8 | Dividir hojas de estilo grandes | Completado |
| 9 | Ejecutar regresión automatizada | Completado |
| 10 | Realizar revisión visual y responsive | Pendiente |
| 11 | Integrar `refactor` en `main` | Pendiente |

## 6. Verificación y resultados

Las pruebas se ejecutaron el 23 de septiembre de 2026 sobre la branch `refactor`.

### Frontend

Comando:

```bash
cd frontend
npm test
```

Resultado:

- 25 archivos de prueba aprobados.
- 84 pruebas aprobadas.
- 0 pruebas fallidas.

### Backend

Comando:

```bash
cd backend
php artisan test
```

Resultado:

- 162 pruebas aprobadas.
- 1499 aserciones aprobadas.
- 0 pruebas fallidas.

También se validaron los imports de CSS, el balance de llaves y la inexistencia de errores de formato en el diff.

## 7. Resultado de la refactorización

El proyecto conserva sus funcionalidades, pero ahora presenta una estructura más modular. Las responsabilidades están mejor delimitadas, se redujo la duplicación, las validaciones y autorizaciones están centralizadas y los componentes nuevos cuentan con pruebas específicas. Esto disminuye el riesgo de modificar una funcionalidad y afectar otra, facilita la lectura del código y permite extender el sistema con menor costo de mantenimiento.

## 8. Trabajo pendiente y mejoras futuras

Para cerrar formalmente la refactorización se recomienda:

1. Realizar una prueba visual de todas las páginas principales.
2. Comprobar el diseño en resoluciones de escritorio, tablet y móvil.
3. Verificar manualmente los flujos completos para los tres roles.
4. Integrar la branch `refactor` en `main` después de la aceptación visual.
5. Como mejora opcional, dividir `InitialDataSeeder.php`, que continúa siendo un archivo grande, aunque no afecta la ejecución habitual de la aplicación.

## 9. Criterios de finalización

La refactorización podrá considerarse cerrada cuando:

- Las suites frontend y backend continúen aprobando.
- No existan errores en la consola ni solicitudes HTTP fallidas durante las pruebas manuales.
- Las vistas mantengan su apariencia y comportamiento en las resoluciones acordadas.
- Los cambios sean revisados e integrados en `main`.

