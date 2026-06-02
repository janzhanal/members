# ORIS mock

Development and autotest sidecar for the members ORIS integration.

## What it does

- exposes an ORIS-compatible `GET/POST /API/`
- stores local races, users, entries, classes, and services as MariaDB columns
- reads real ORIS for unstored or overlay races, then applies local column overlays and local deletions before returning JSON
- serves proxy-only event, entry, and service requests entirely from the mock database without calling real ORIS
- treats `NULL` overlay columns as "leave the upstream value unchanged"
- composes proxy-only objects from columns and defaults on every request
- keeps mutating calls local-only: `createEntry`, `updateEntry`, `deleteEntry`, `createPerson`, `editPerson`, `createClubUser`, `editClubUser`
- provides an admin UI at `/__admin`
- provides JSON endpoints under `/__admin/api/*` for automatic tests
- supports network disturbance modes: `normal`, `force_client_error`, `service_down`, `delay`, `hang`, `close_connection`

## Run inside the dev web container

```bash
npm run mock:oris
```

The server listens on port `10301` by default.

## Admin API

- `GET /__admin/api/settings`
- `POST /__admin/api/settings`
- `POST /__admin/api/reset`
- `GET /__admin/api/races`
- `POST /__admin/api/races`
- `PUT /__admin/api/races/:id`
- `DELETE /__admin/api/races/:id`
- `POST /__admin/api/users`
- `DELETE /__admin/api/users/:userId?regNo=ZBM9999&sport=1&year=2026`
- `POST /__admin/api/races/:eventId/entries`
- `DELETE /__admin/api/races/:eventId/entries/:entryId`
- `DELETE /__admin/api/entries/:entryId`
- `POST /__admin/api/races/:eventId/services`

Race create/update accepts `proxy_only` (`1` for mock/local-only, `0` for upstream overlay).
When an upstream overlay race is saved, the mock fetches the upstream event and stores all returned classes in the mock database so later local entry mutations can resolve `class` IDs without another event lookup.
Successful upstream `getEvent` requests through the mock also refresh the stored class snapshot.

Example service-down mode:

```bash
curl -X POST http://127.0.0.1:10301/__admin/api/settings \
  -H 'Content-Type: application/json' \
  -d '{"mode":"service_down","forceStatusCode":503}'
```

Example long delay:

```bash
curl -X POST http://127.0.0.1:10301/__admin/api/settings \
  -H 'Content-Type: application/json' \
  -d '{"mode":"delay","responseDelayMs":30000}'
```

Example:

```bash
curl -X POST http://127.0.0.1:10301/__admin/api/races \
  -H 'Content-Type: application/json' \
  -d '{"id":"990001","name":"Proxy test race","date":"2026-06-01","entryStart":"2026-05-25 20:00:00","entryDate1":"2026-05-30 20:00:00","classes":[{"ID":"99000101","Name":"H21","Fee":150}]}'
```
