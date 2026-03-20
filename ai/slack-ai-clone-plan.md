## Plan: Build a Slack-Style LAN Chat + AI Clone in /ai/

This plan combines the reusable DB-first AI panel foundation from Plan A with the product shape from Plan B: a local LAN chat system with channels, direct messages, webhook-fed log rooms, and AI as an optional participant inside rooms rather than the whole app.

The result is a local-first XAMPP app that is immediately useful for humans, scripts, and AI together, while keeping the same core architecture portable to a later Linux/public/business deployment.

**Product Goal**
Create a lightweight Slack-style chat system under /ai/ for local LAN use, with:
- channels like #general and #log
- direct messages between users
- optional AI personas invited into rooms
- incoming webhooks that post into rooms
- all important configuration stored in MySQL
- reusable PHP library code under /ai/lib/
- simple local auth now, stronger auth later on the public/Linux version

**Core Product Model**
The app is not just an AI panel anymore.
It is a room-based messaging system where messages can come from:
- user -> user
- user -> room
- user -> AI
- AI -> room
- webhook -> room
- system -> room

AI is treated as a participant type, not as a separate app.
That keeps the system useful even when AI is disabled.

**MVP Scope**
Included in v0.1:
- login/logout
- local users and roles
- channels
- DM rooms
- room participants
- unified message storage
- #general seed room
- #log seed room
- incoming webhook endpoint
- AI personas
- optional AI replies in rooms
- DB-backed settings and settings metadata
- reusable PHP library foundation
- dark readable desktop-first UI

Deferred until later:
- file uploads
- emoji reactions
- typing indicators
- presence
- voice/video
- advanced search
- full tool execution engine
- autonomous AI settings mutation
- hardened public-facing auth
- API auth and rate limiting
- advanced moderation or compliance features

**Architecture Direction**
Keep the DB-first settings engine from the original AI panel plan, but shift the chat model from AI-thread-centric to room-centric.

Use these app surfaces:
- /ai/index.php for the main chat app
- /ai/admin.php for settings, rooms, personas, webhook management, and provider admin
- /ai/install.php for setup and seeding
- /ai/api.php or /ai/ajax/*.php for JSON endpoints
- /ai/webhook.php for inbound webhook posting

**Recommended Folder Layout**
/ai/
- index.php
- admin.php
- install.php
- api.php
- webhook.php
- router.php
- /lib/
  - bootstrap.php
  - db.php
  - auth.php
  - settings.php
  - settings_meta.php
  - rooms.php
  - messages.php
  - users.php
  - ai_provider.php
  - personas.php
  - webhook.php
  - permissions.php
  - ui.php
  - util.php
- /ajax/
  - login.php
  - logout.php
  - load_room.php
  - send_message.php
  - create_room.php
  - save_setting.php
  - save_room.php
- /view/
  - layout.php
  - chat.php
  - admin.php
  - login.php
  - parts/
- /assets/
  - css/
  - js/
  - icons/

**Database Schema**
Keep settings-oriented tables from Plan A and add the room-centric chat structures from Plan B.

1. users
- id
- username
- display_name
- password_hash
- is_active
- created_at
- updated_at

2. roles
- id
- role_key
- name
- description

3. user_roles
- id
- user_id
- role_id
- created_at

4. settings
- id
- category
- section
- setting_key
- setting_value
- setting_type
- description
- is_public
- is_editable
- updated_by
- updated_at

5. settings_meta
- id
- setting_key
- label
- input_type
- options_json
- validation_json
- help_text
- sort_order
- tab_name

6. rooms
- id
- room_key
- room_type
- name
- slug
- is_private
- created_by
- created_at
- updated_at
- settings_json

Room types for MVP:
- channel
- dm
- group
- log

7. room_participants
- id
- room_id
- participant_type
- participant_id
- can_post
- can_invite
- joined_at

Participant types for MVP:
- user
- persona
- webhook

8. messages
- id
- room_id
- sender_type
- sender_id
- message_text
- message_type
- reply_to_id
- status
- meta_json
- created_at

Sender types:
- user
- persona
- webhook
- system

Message types:
- text
- notice
- log
- ai_reply

9. personas
- id
- persona_key
- name
- system_prompt
- style_notes
- is_enabled
- is_default
- settings_json
- updated_at

10. ai_providers
- id
- provider_key
- name
- driver
- base_url
- api_key
- model_default
- is_enabled
- priority
- settings_json
- updated_at

11. ai_models
- id
- provider_id
- model_key
- label
- context_window
- max_tokens
- temperature_default
- is_enabled
- supports_tools
- supports_images
- supports_reasoning
- settings_json

12. webhook_sources
- id
- name
- webhook_key
- target_room_id
- is_enabled
- source_type
- created_at
- updated_at

Optional next table after MVP:
- audit_log

**Room-Level AI Behavior**
Each room should support room-level AI controls through settings_json or a dedicated room settings table later.

For MVP, support:
- ai_enabled
- ai_persona_id
- ai_trigger_mode

Trigger modes:
- off
- manual
- always

Behavior:
- off: AI never responds
- manual: AI responds only when tagged or explicitly invoked
- always: AI responds to all eligible messages in the room

This is enough for:
- DM with wife only
- DM with AI only
- room with you + wife + AI
- #general with optional AI
- #log with no AI

**Phased Implementation Plan**
1. Phase 1 - Foundation and Installer
2. Create DB schema for users, roles, user_roles, settings, settings_meta, rooms, room_participants, messages, personas, ai_providers, ai_models, webhook_sources.
3. Build /ai/lib/bootstrap.php and /ai/lib/db.php for DB connection, sessions, helper loading, app config bootstrap, and role context.
4. Build /ai/lib/auth.php for local login/session auth.
5. Build /ai/install.php to create schema, seed admin user, seed default roles, seed default rooms (#general, #log), seed default persona, seed default provider, seed initial models, and seed starter settings. Lock installer after success.

6. Phase 2 - Settings Engine and Admin
7. Implement /ai/lib/settings.php for typed get/set/category access with role-aware protection for sensitive keys.
8. Implement settings_meta-driven form rendering so admin pages are data-driven instead of hardcoded.
9. Build /ai/admin.php with tabs for app settings, AI settings, providers, models, personas, rooms, users, and webhook sources.

10. Phase 3 - Room and Message Core
11. Implement /ai/lib/rooms.php for room creation, DM creation, participant management, room listing, and room lookup.
12. Implement /ai/lib/messages.php for posting, loading, paging, and metadata capture.
13. Build unified message flow so one system supports human chat, AI chat, and webhook log messages.
14. Build the main /ai/index.php chat UI with left sidebar for rooms/DMs, center message panel, composer, and optional right panel for room info or AI controls.

15. Phase 4 - AI Participation Layer
16. Implement /ai/lib/ai_provider.php with one OpenAI-compatible driver first.
17. Implement /ai/lib/personas.php to manage persona definitions and runtime selection.
18. Add room-level AI participation logic so persona replies can be generated according to ai_trigger_mode.
19. Support DM rooms made of one user plus one persona to provide private AI chat without separate thread architecture.

20. Phase 5 - Webhooks and Log Rooms
21. Build /ai/webhook.php and webhook validation using webhook_sources.webhook_key.
22. Route incoming webhook payloads into target rooms, especially #log.
23. Normalize inbound payloads into unified message rows with sender_type = webhook and message_type = log.

24. Phase 6 - Endpoint Layer and Security
25. Implement AJAX endpoints for login, logout, room loading, message sending, room creation, and settings saves.
26. Add endpoint-level permission checks for admin and member actions.
27. Add CSRF protection, input validation, and session checks for all write routes.
28. Keep auth simple for local use now, but isolate auth and permission code so the Linux/public version can harden later without a rewrite.

29. Phase 7 - Verification and Hardening
30. Verify installer lock behavior.
31. Verify local login/logout flow.
32. Verify room creation and DM creation.
33. Verify #general and #log seeding.
34. Verify message posting and reload.
35. Verify webhook delivery into #log.
36. Verify AI participation in manual and always modes.
37. Verify non-admin users cannot change sensitive settings.
38. Verify the app remains functional with AI disabled.

**Seed Data for MVP**
Seed roles:
- admin
- member
- viewer

Seed rooms:
- #general
- #log

Seed persona:
- Assistant

Seed settings examples:
- app.site_name
- ui.theme
- ui.font_size
- auth.remember_me_enabled
- chat.default_persona
- ai.default_provider
- ai.default_model
- ai.dm_enabled
- room.ai_default_trigger_mode
- webhook.enabled
- webhook.log_room_default

**UI Direction**
Use a Slack-lite or Discord-lite layout, but keep it simple.

Layout:
- left rail: channels, DMs, personas shortcut, settings shortcut
- center: room header, messages, composer
- right panel: room members, persona info, room AI mode, webhook source info

MVP visual goals:
- dark but readable theme
- large, comfortable text
- fast room switching
- clean room badges for AI-enabled rooms
- visible distinction between user, AI, webhook, and system messages

**What Carries Forward to the Public/Linux Version**
The following should stay reusable:
- DB schema direction
- settings engine
- provider abstraction
- persona system
- room/message model
- webhook ingestion pattern
- room-level AI trigger model

The following should be hardened later:
- auth
- permission matrix depth
- webhook validation
- audit logging
- API security
- rate limiting
- admin controls

**Decisions**
- Product framing is now a LAN chat system with AI-enabled rooms, not a pure AI control panel.
- AI is a participant type inside rooms, not the only communication model.
- The original DB-first settings engine stays and becomes the configuration backbone.
- The MVP targets local XAMPP/LAN use first, with portability to Linux/public deployment later.

**Verification Checklist**
1. Installer creates schema and seed data once, then locks.
2. Admin can log in and manage settings, personas, rooms, and providers.
3. Member can log in, join rooms, send messages, and use DMs.
4. #general and #log exist after install.
5. Incoming webhook posts appear in #log.
6. AI can be enabled per room and responds according to room trigger mode.
7. A DM room with user + persona works as a private AI chat.
8. Message history persists and reloads correctly.
9. Sensitive settings remain protected from non-admin users.
10. Core chat remains useful even when no AI provider is configured.

**Recommended Build Order**
1. Installer, schema, auth, settings foundation.
2. Rooms, participants, messages, and chat UI.
3. Admin for users, rooms, settings, and personas.
4. Provider integration and room-level AI replies.
5. Webhook ingestion into #log.
6. Hardening, validation, and cleanup.