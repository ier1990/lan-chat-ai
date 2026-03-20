## Plan: Build AI Panel MVP in /ai/

Create a v0.1 database-first AI panel under /ai/ that is chat-first, settings-driven, and reusable, while including a multi-user role foundation from the start. Use installer-seeded data, keep behavior controlled by MySQL tables, and defer advanced automation until the core flow is stable.

**Steps**
1. Phase 1 - Foundation and Installer
2. Define schema migrations for core tables: settings, settings_meta, ai_providers, ai_models, personas, users, roles, user_roles, chat_threads, chat_messages. Include minimal indexes and foreign keys for thread-message and provider-model relationships.
3. Create bootstrap and DB access layer in /ai/lib/ to centralize DB connection, session bootstrap, role context loading, and app-wide helper wiring.
4. Implement install flow in /ai/install.php to validate DB access, create schema, seed default admin user, seed default role set, seed default provider/model/persona, seed 20-30 starter settings, then set an install lock flag. Depends on step 2 and step 3.
5. Phase 2 - Settings and Admin
6. Implement settings service for typed get/set and category reads, including editable and visibility controls, with role-aware write checks for sensitive keys.
7. Implement settings metadata renderer that builds admin forms from settings_meta definitions so new keys can be added without new page code.
8. Build /ai/admin.php for role-gated settings management tabs (app, provider, model, UI, memory), including validation and audit fields on update. Depends on step 6 and step 7.
9. Phase 3 - Provider and Chat Core
10. Implement provider adapter abstraction with one OpenAI-compatible driver first (usable with OpenAI, Ollama-compatible endpoints, or LM Studio style /v1 endpoints).
11. Implement model selection and runtime resolution (active provider, active model, request options) from DB settings and provider/model tables.
12. Implement chat thread/message services and storage with persona binding per thread and basic token/latency metadata capture.
13. Build /ai/index.php as the main chat UI with thread list, active conversation, message input, provider/model badge, and persona selector. Depends on step 10, step 11, and step 12.
14. Phase 4 - AJAX Endpoints and Access Control
15. Implement AJAX endpoints for send_message, load_chat, and save_setting with JSON responses and consistent error contracts.
16. Add role checks at endpoint boundary: admin/editor roles can update allowed settings, restricted roles are read-only for sensitive categories.
17. Add CSRF/session protections and request validation for all writable routes.
18. Phase 5 - Verification and Hardening
19. Add installer lock verification and re-entry protection tests.
20. Add integration checks for settings reads/writes, provider call success path, chat persistence, and role-based settings protection.
21. Perform manual UX pass to confirm chat-first flow, readable dark large-text style, and working admin form auto-generation.
22. Mark advanced items (tools execution framework, AI self-modifying settings workflow, advanced memory summarization policies, provider failover strategy) as explicit post-MVP backlog. Parallel with step 20 and step 21.

**Relevant files**
- c:/xampp/htdocs/ai/README.md - source vision and scope constraints, including v0.1 boundaries and DB-first architecture.
- c:/xampp/htdocs/ai/install.php - installer entrypoint, schema creation trigger, seed orchestration, install lock logic.
- c:/xampp/htdocs/ai/index.php - chat-first shell and interaction surface.
- c:/xampp/htdocs/ai/admin.php - role-gated settings panel generated from metadata.
- c:/xampp/htdocs/ai/lib/bootstrap.php - app bootstrap, session, role context, shared initialization.
- c:/xampp/htdocs/ai/lib/db.php - PDO wrapper and transaction helpers.
- c:/xampp/htdocs/ai/lib/settings.php - typed settings API and write guards.
- c:/xampp/htdocs/ai/lib/ai_provider.php - provider abstraction and request routing.
- c:/xampp/htdocs/ai/lib/chat.php - thread/message persistence and retrieval.
- c:/xampp/htdocs/ai/ajax/send_message.php - chat request endpoint and response contract.
- c:/xampp/htdocs/ai/ajax/save_setting.php - validated settings update endpoint with role checks.
- c:/xampp/htdocs/ai/ajax/load_chat.php - thread/message retrieval endpoint.

**Verification**
1. Run installer once and confirm table creation plus seed data creation; verify second installer run is blocked.
2. Validate login/session role context for admin and non-admin users.
3. Validate settings form auto-render from settings_meta and successful write/read roundtrip for non-sensitive keys.
4. Validate sensitive key update is rejected for non-privileged roles and accepted for admin role.
5. Send chat prompt through configured provider and verify response persistence in chat_messages with metadata fields.
6. Validate thread creation, thread reload, persona switching, and model switching from DB-backed options.
7. Validate all AJAX endpoints return stable JSON shape on both success and error.

**Decisions**
- Included scope: v0.1 MVP from README with multi-user role support included in foundation.
- Excluded from MVP: full tool execution engine, deep memory policy automation, AI autonomous settings mutation workflows beyond guarded human role actions.
- Assumption: OpenAI-compatible provider interface is the first implemented adapter for fastest compatibility with local and hosted backends.

**Further Considerations**
1. Role model depth: Start with admin/editor/viewer for MVP, then expand to per-capability matrix later.
2. Migration strategy: Prefer versioned SQL migration files after MVP to avoid installer-only schema evolution.
3. Auditability: Add settings change log table in next increment if operational traceability becomes required.