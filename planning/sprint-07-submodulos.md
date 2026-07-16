# Sprint Submódulos — Chat, Respuestas rápidas, Notas internas, Recordatorios

> Cuatro submódulos del inbox de WhatsApp. **Plantillas queda fuera** (se planifica
> aparte por su dependencia del gateway y la aprobación de Meta).
>
> Los cuatro son **agnósticos de gateway**: se construyen una vez y funcionan igual
> en Kommo o Cloud API. Nada de esto toca el envío por el proveedor.

## Decisiones / supuestos (confirmar si algo cambia)

| Tema | Decisión |
|---|---|
| Chat | **Página propia** en el menú (lista de conversaciones + thread), no solo el tab por contacto |
| Respuestas rápidas | **Globales + por usuario** (cada asesor ve las suyas y las del equipo) |
| Notas internas | Visibles para **todo el equipo**, inline en el hilo, **nunca** se mandan al cliente **ni** al agente IA |
| Recordatorios | Tabla propia + comando agendado; avisa al **usuario asignado**; se apoya en el scheduler que ya corre |
| Variables en respuestas rápidas/plantillas cortas | Sustitución básica: `{{nombre}}` → nombre del contacto |

## Cambios de esquema (todos nuevos, sin tocar lo existente)

```
whatsapp_messages         + user_id (nullable, FK users)   ← autor de notas y de envíos humanos
whatsapp_quick_replies    (nueva)
whatsapp_reminders        (nueva)
```

Las notas **reutilizan `whatsapp_messages`** con `type='note'` y `direction='internal'`,
así aparecen en el hilo en orden cronológico. El envío por gateway nunca se dispara
para ellas, y quedan excluidas del historial que ve el agente IA.

---

# Módulo A · Notas internas  `[BAJO]`

Notas del equipo dentro de la conversación. No van al cliente.

### HU-N1 · Esquema + modelo — 0.5d `[BACK]`
- Migración `user_id` en `whatsapp_messages`. Tipo `note`, dirección `internal`.
- `Message` gana relación `author()` (User).
**AC:** una nota se guarda con `type=note`, `direction=internal`, `user_id` del autor.

### HU-N2 · Endpoint + aislamiento — 0.5d `[BACK]`
- `POST admin/whatsapp/conversations/{id}/notes` `{body}`.
- Nunca despacha `SendMessage`.
- `AgentPayload::history()` y el evento **excluyen** `direction='internal'` (las notas no se filtran al agente IA ni al cliente).
**AC:** crear una nota no genera ningún envío a WhatsApp; el evento al agente no la incluye.

### HU-N3 · Front: modo Mensaje / Nota — 1d `[FRONT]`
- Toggle en la barra de escritura: **Mensaje** (verde, va a WhatsApp) / **Nota** (amarillo, interna).
- Las notas se renderizan distinto (fondo ámbar, "Nota · Juan · 14:03"), a lo ancho.
**AC:** en modo Nota, el texto se guarda como interna y se ve en el hilo sin salir al cliente.

---

# Módulo B · Respuestas rápidas  `[BAJO]`

Snippets predefinidos para responder más rápido.

### HU-Q1 · Esquema + modelo + repo — 0.5d `[BACK]`
- `whatsapp_quick_replies`: `id`, `user_id` (nullable = global), `shortcut`, `title`, `content`, timestamps.
**AC:** modelo + repo; una respuesta global (user_id null) y una por usuario conviven.

### HU-Q2 · CRUD + pantalla de gestión — 1d `[BACK · FRONT]`
- Rutas CRUD `admin/whatsapp/quick-replies` + `index` que devuelve las disponibles al usuario (globales + propias).
- Pantalla simple de gestión (listar/crear/editar/borrar).
**AC:** un asesor crea/edita/borra sus respuestas; ve también las globales; no ve las de otros.

### HU-Q3 · Selector en la barra de escritura — 1d `[FRONT]`
- Escribir `/` abre un buscador; filtra por `shortcut`/`title`; Enter inserta el `content`.
- Sustitución de variables: `{{nombre}}` → nombre del contacto.
**AC:** `/saludo` inserta la respuesta con el nombre del contacto ya reemplazado.

---

# Módulo C · Chat (página propia)  `[MEDIO]`

Una sección "WhatsApp" en el CRM con lista de conversaciones + hilo, estilo WhatsApp Web.
Es donde viven naturalmente los otros tres.

### HU-C1 · Menú + ACL + shell de la página — 1d `[BACK · FRONT]`
- Ítem de menú `whatsapp` (`config/menu.php`) + entrada ACL.
- Ruta `admin/whatsapp` con layout de dos columnas (lista | hilo).
**AC:** aparece "WhatsApp" en el menú y abre la página con las dos columnas.

### HU-C2 · Endpoint de lista de conversaciones — 1d `[BACK]`
- `GET admin/whatsapp/conversations`: paginado, búsqueda por nombre/teléfono, filtros
  (todas / sin asignar / no leídas / mías), orden por `last_message_at`.
- Cada ítem: id, nombre, teléfono, gateway, preview del último mensaje, hora, `unread_count`, `ai_enabled`.
**AC:** la lista pagina, busca y filtra; el preview y el contador de no leídos son correctos.

### HU-C3 · Front: lista + selección + hilo — 1.5d `[FRONT]`
- Componente de lista de conversaciones (con no-leídos, avatar, hora relativa).
- Al seleccionar, carga el hilo **reutilizando el componente inbox ya existente**.
- Polling de la lista y del hilo (Reverb queda para otro sprint).
**AC:** seleccionar una conversación abre su hilo; los mensajes nuevos actualizan la lista.

### HU-C4 · Bandeja "Sin asignar" + convertir — 1d `[FRONT · BACK]`
- Filtro de conversaciones sin `person_id`; botón "Crear contacto/lead" que las vincula.
**AC:** una conversación de número desconocido es visible y convertible desde la bandeja.

---

# Módulo D · Recordatorios  `[MEDIO]`

Programar un follow-up en una conversación y recibir aviso.

### HU-R1 · Esquema + modelo — 0.5d `[BACK]`
- `whatsapp_reminders`: `id`, `conversation_id` FK, `user_id` FK (a quién avisar),
  `created_by` FK, `body`, `remind_at`, `status` (pending/done/cancelled), `completed_at`,
  timestamps, índice `(status, remind_at)`.
**AC:** crear un recordatorio pendiente con fecha/hora futura.

### HU-R2 · Endpoints + comando agendado — 1d `[BACK]`
- `POST/GET/PATCH admin/whatsapp/conversations/{id}/reminders` (crear, listar, completar/cancelar).
- `GET admin/whatsapp/reminders` → los pendientes del usuario.
- Comando `whatsapp:reminders:fire` agendado `everyMinute`: marca los vencidos y genera
  el aviso (registra una Activity de Krayin sobre el lead + queda "vencido" en el chat).
**AC:** un recordatorio con `remind_at` pasado se marca vencido y aparece el aviso.

### HU-R3 · Front: crear / listar / marcar hecho — 1d `[FRONT]`
- Acción en el hilo: "Recordarme…" con selector de fecha/hora + nota.
- Indicador en el header del chat cuando hay recordatorios vencidos.
- Lista de pendientes del asesor.
**AC:** se crea un recordatorio desde el chat, se ve en la lista y se puede marcar hecho.

### HU-R4 · Infra — 0.25d `[DEVOPS]`
- Confirmar que `php artisan schedule:run` corre cada minuto en el server (hoy ya agenda
  `inbound-emails:process`, así que el scheduler está vivo — solo verificar).
**AC:** el comando de recordatorios se dispara solo, sin intervención.

---

## Orden de implementación

1. **Notas internas** — chico, cero riesgo, útil de inmediato.
2. **Respuestas rápidas** — chico, mejora la productividad del asesor.
3. **Chat (página propia)** — el contenedor donde se integran los demás.
4. **Recordatorios** — depende del scheduler; se prueba solo.

**Total estimado: ~2 semanas** (1 dev). Front y back en paralelo lo comprimen.

## Definición de Hecho (global)
- `pint` limpio, `php -l` OK.
- Nada de esto dispara envíos al gateway salvo el mensaje real (las notas nunca).
- Las notas no aparecen en el evento/historial que ve el agente IA.
- Verificado contra la BD real en el contenedor.

## Fuera de alcance (a propósito)
- **Plantillas** (submódulo aparte).
- **Reverb / tiempo real** (sigue polling).
- Notificaciones push/email de recordatorios (por ahora Activity + indicador en el chat).
