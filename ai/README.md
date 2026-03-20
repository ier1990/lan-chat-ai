# AI Chat (XAMPP / PHP / MySQL)

AI Chat is a database-driven team chat and AI orchestration app under `/ai/`.

It runs on XAMPP (Apache + PHP + MySQL), keeps almost all runtime config in the database, and provides a standalone admin panel for managing users, rooms, providers, AI users, personas, and webhooks.

## Highlights

- Chat-first UI with channels and DMs
- Role-based auth (`admin`, `member`, `ai`)
- AI provider routing via OpenAI-compatible APIs
- AI Users (bot identities) with per-user endpoint/model/key/persona
- OpenRouter-friendly AI User headers (`HTTP-Referer`, `X-Title`)
- Persona management with:
  - create/edit/delete
  - curated templates
  - JSON import/export
- Rooms management with:
  - create channels
  - edit room metadata and AI trigger flags
  - DM creation (user or persona)
  - webhook key create/rotate/delete per room
  - copyable webhook URL and curl examples
- Inbound webhook receiver for posting into rooms
- Data-driven settings UI (settings + settings_meta)

## App Paths

- `/ai/` - main chat app
- `/ai/admin.php` - admin panel
- `/ai/admin.php?standalone=1` - admin in standalone layout (no chat sidebar)
- `/ai/admin.php?standalone=1&section=rooms` - rooms operations
- `/ai/admin.php?standalone=1&section=personas` - personas operations
- `/ai/install.php` - first-time install/seed flow
- `/ai/webhook.php?key=...` - inbound webhook endpoint

## Requirements

- XAMPP on Windows (or equivalent LAMP stack)
- PHP 8.2+
- MySQL/MariaDB
- Apache with mod_php or PHP-FPM setup compatible with XAMPP

## Quick Start

1. Put this project in `C:\xampp\htdocs\ai`.
2. Edit `config.php` with DB credentials.
3. Open `/ai/install.php`.
4. Complete install (schema + default seed data).
5. Sign in and open `/ai/`.

If install already ran, `.installed` is present and the installer is locked.

## Architecture Overview

Top-level files:

- `index.php` - chat app entry
- `admin.php` - admin controller + data orchestration
- `install.php` - install/seed flow
- `webhook.php` - webhook ingest
- `config.php` - local environment DB config

Core libraries in `lib/`:

- `bootstrap.php` - init, DB connect, session/auth bootstrap
- `settings.php`, `settings_meta.php` - typed settings + metadata-driven admin rendering
- `rooms.php`, `messages.php`, `users.php`, `personas.php` - app domain logic
- `ai_provider.php` - provider calls/model sync
- `ai_users.php` - AI user config + DM AI-user reply routing helpers

Views/assets:

- `view/layout.php` + view partials
- `assets/css/main.css`
- `assets/js/chat.js`

Database schema:

- `db/schema.sql`

## Admin Sections

`admin.php` supports these section areas:

- Settings
- Rooms
- Users
- AI Users
- Personas
- Providers
- Webhooks

### Rooms

You can:

- create channels
- edit room name/slug/privacy/AI flags
- create DMs targeting users or personas
- create/rotate/delete room webhook keys
- copy webhook URL and curl command

DM rows are labeled contextually (for example: `DM Self`, `DM AI`, or `DM` with participant detail).

### AI Users

AI Users are normal users with `ai` role plus per-user provider config.

Config includes:

- provider key
- endpoint base URL
- model
- API key
- persona
- optional headers JSON
- OpenRouter helper fields

DM messages to AI users can auto-route to that AI user's configured endpoint and save reply metadata.

### Personas

You can:

- create/edit/delete personas
- mark enabled/default
- maintain system prompt/style/settings JSON
- insert curated templates
- export one/all personas as JSON
- import JSON payloads (single object, list, or `{ "personas": [...] }`)

## Webhook Usage

Room webhooks post into the room as webhook messages.

Request shape:

- Method: `POST`
- URL: `/ai/webhook.php?key=YOUR_KEY`
- Content type: JSON or form
- Body: provide `text` (or `message`)

Example:

```bash
curl -X POST "http://YOUR_HOST/ai/webhook.php?key=YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{"text":"hello from $(hostname)"}'
```

## Configuration Philosophy

This app is DB-first:

- Operational settings are stored in `settings`
- Form/UI metadata is in `settings_meta`
- Provider/model/persona/room behaviors are data-backed

Goal: reduce hardcoded config sprawl and keep admin changes in-app.

## Security Notes

- Keep admin account credentials strong.
- Rotate webhook keys if leaked.
- Treat provider API keys as secrets.
- For local-only AI endpoints on trusted LAN, test connectivity from the server host (Apache/PHP machine), not just from your browser.

## Troubleshooting

- If DB connection fails, verify `config.php` and MySQL service.
- If installer is inaccessible after setup, remove `.installed` only if you intentionally want to reinstall.
- If AI replies fail, verify endpoint reachability from the app host and validate model/key.
- If admin debug logs are noisy, disable Debug Mode in admin settings (`app.debug_mode`).

## License / Notes

This repository is intended as a practical internal-style app scaffold under XAMPP.
Adjust branding, defaults, and security posture before internet exposure.
