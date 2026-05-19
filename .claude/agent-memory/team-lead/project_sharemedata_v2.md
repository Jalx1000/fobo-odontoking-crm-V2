---
name: sharemedata-v2-integration-plan
description: Plan de integración ShareMeData API v2 — brechas identificadas, endpoints nuevos de patients, riesgos críticos de base URL y schema Dropbox
metadata:
  type: project
---

El plan de integración ShareMeData API v2 fue creado el 2026-05-19 en `planning/00-sharemedata-api-v2/00.agent-task.md`.

**Endpoints nuevos (no integrados) de la API v2:**
- `GET /api/patients` — buscar paciente por teléfono
- `POST /api/patients/user` — crear paciente en SMD
- `PATCH /api/patients/{id}` — actualizar paciente en SMD
- Sincronización de citas via Dropbox (JSON por evento, carpeta por día)

**Lo que ya está integrado (base URL `/api/calendar`):**
- `GET /api/calendar/specialties` → `getSpecialties()` / `syncSpecialties()`
- `GET /api/calendar/schedule/availability` → `checkAvailability()`
- `POST /api/calendar/schedule/createEvent` → `createEvent()`
- Webhook entrante en `ShareMeDataWebhookController`

**Riesgo crítico R1:** Los endpoints de patients usan base URL `https://gamma.sharemedata.com/api` (sin `/calendar`). El servicio actual tiene hardcodeado `/api/calendar`. Necesita `SHAREMEDATA_PATIENTS_URL` como variable separada.

**Riesgo crítico R2:** El Webhook actual espera schema `physician/patient/slot`; el PDF Dropbox usa `attendances[]/owner`. Necesita refactor a `IncomingAppointmentService` antes de que SMD actualice su formato.

**Acción externa bloqueante:** Crear cuenta Dropbox y que SMD la añada a carpeta compartida (para Fase 3).

**Why:** Se detectaron estas brechas al analizar el PDF v2 vs el código existente en `ShareMeDataService.php`, `AppointmentService.php` y `ShareMeDataWebhookController.php`.

**How to apply:** Al trabajar en cualquier tarea relacionada con ShareMeData, consultar primero el plan en `planning/00-sharemedata-api-v2/00.agent-task.md` para no duplicar trabajo ni ignorar brechas ya identificadas.
