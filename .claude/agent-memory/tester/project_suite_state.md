---
name: project-suite-state
description: Estado de la suite de tests — cobertura actual, tests passing, gaps conocidos
metadata:
  type: project
---

Suite a 2026-05-19: **83 passed, 2 skipped, 0 failed** (274 assertions, ~12s).

Los 2 skipped son intencionales y pre-existentes (SpecialtyApiTest y IaHistoryTest).

## Archivos de test relevantes para SMD/Persons

| Archivo | Tests | Cubre |
|---|---|---|
| `tests/Feature/PersonSmdSyncTest.php` | 18 | syncSmd, searchSmd, listeners syncToSmd/updateInSmd |
| `tests/Feature/ShareMeDataWebhookErrorTest.php` | 5 | auth webhook, validación payload |
| `tests/Feature/InsuranceVerificationTest.php` | 12 | verificación seguros, caché, mapeo estados |
| `tests/Feature/EntitySyncTest.php` | 2 | sync bidireccional CI/Seguro Person↔Lead |

## Gaps que quedaron sin cobertura (bajo prioridad o requieren refactor mayor)

- `syncSmd 403`: bouncer()->hasPermission devuelve true para User::find(1) en testing → el 403 no se puede disparar fácilmente sin mockear Bouncer
- `searchSmd sin autenticación`: las rutas admin requieren auth, no hay test de 401/redirect para usuario no logueado en este endpoint
- `updateInSmd con email como tercer fallback`: el listener busca por email si no tiene teléfono; el flujo completo de ese branch no está cubierto

**Why:** El 403 de syncSmd depende de Bouncer, que en testing siempre otorga permisos al user admin. Requeriría crear un usuario sin el permiso `contacts.persons.edit`.

**How to apply:** Si se implementa un segundo rol de usuario (ej. "recepcionista sin edición"), añadir test del 403 en PersonSmdSyncTest.
