# Sesión expirada durante formularios

## Comportamiento y alcance

`CuidadoApi.fetchJson` rechaza las respuestas HTTP no exitosas. Un 401 de una operación protegida se identifica como `session_expired`, limpia la sesión y, para escrituras, muestra el aviso amigable con enlace al login. No reintenta la solicitud ni redirige automáticamente.

Los formularios revisados esperan la respuesta antes de ejecutar resets, mensajes de éxito, navegación o actualización del perfil. Un 401 interrumpe ese camino. Los datos que siguen visibles en el DOM no son un borrador persistido: al navegar o recargar pueden perderse. No se agregó almacenamiento, recuperación, autosave ni reenvío después de iniciar sesión.

## Operaciones revisadas

| Flujo | Escritura por envío | Después de confirmar éxito |
|---|---|---|
| Administrador: alta/edición de expediente con medicamentos | Un POST/PUT con expediente y asignaciones en el mismo payload | Mensaje y regreso al listado. |
| Administrador: usuarios, perfil, rutinas e inventario | Un POST/PUT/PATCH según la acción | Reset, actualización visual o lecturas de refresco según el formulario. |
| Profesional: rutina con actividades y notas | Un POST/PUT por formulario; rutina y nota son envíos independientes | Reset y lecturas para refrescar el panel. |
| Profesional: solicitudes de turno/vacaciones | Un POST por solicitud | Lecturas de refresco; vacaciones limpia el formulario tras éxito. |
| Familiar: edición de perfil | Un PUT `/me` | Actualiza identidad y campos visibles después de la respuesta. |
| Administrador/profesional: registro de incidente | Un POST | Consulta el listado; si falla el refresco informa que el incidente sí se guardó. |

No se encontraron escrituras encadenadas para completar un mismo formulario en esos flujos. Una lectura posterior puede fallar después de un guardado confirmado: eso no convierte el guardado anterior en parcial ni autoriza repetir la escritura. El formulario de incidentes ya distingue ese caso.

## Límite de la garantía

El frontend envía una sola escritura y no presenta el rechazo como éxito; no puede, por sí mismo, dividir esa petición en guardados parciales. Esto no demuestra la atomicidad interna del servidor. Las rutas inspeccionadas están protegidas por autenticación y permisos; el servicio de expedientes y la actualización de perfil ya contienen transacciones. No se modificó backend ni se ejecutaron sus pruebas en este trabajo. La comprobación de persistencia real ante fallos internos corresponde a las pruebas de integridad del backend existentes.

## Cobertura y verificación

`frontend/tests/dom/form-session-expiration.test.js` utiliza los formularios/acciones reales y `CuidadoApi` con respuestas HTTP simuladas. Cubre administrador, profesional y familiar con sesión válida, 401 y 422. Comprueba una única solicitud de escritura, ausencia de reintentos, conservación de campos ante rechazo, ausencia de flujo de éxito y mantenimiento del mensaje/enlace del Commit 1.

Desde `frontend`:

```sh
npm test -- tests/dom/form-session-expiration.test.js tests/unit/api-config.test.js tests/unit/browser-api.test.js tests/unit/professional-routines-actions.test.js tests/dom/older-adult-create.test.js tests/dom/older-adult-create-error.test.js
npm test
```

Prueba manual: completar un expediente con medicamento, una rutina profesional y un perfil familiar; dejar expirar la sesión y enviar. Verificar el aviso, que no aparezca éxito ni se vacíen los datos como guardados, y que el enlace lleve al login sin reenvío automático. No se exige recuperar campos después de navegar al login.
