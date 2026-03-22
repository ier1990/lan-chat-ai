<?php
/**
 * admin/helpers.php — Shared bootstrap helpers for the admin panel.
 * Included once by index.php before any handler or view.
 */

function _ensureDebugModeSetting(): void
{
    DB::query(
        'INSERT IGNORE INTO settings
         (category, section, setting_key, setting_value, setting_type, description, is_public, is_editable, is_sensitive)
         VALUES (?,?,?,?,?,?,?,?,?)',
        ['app', 'general', 'app.debug_mode', '0', 'bool', 'Log request routing/debug details to #log room.', 0, 1, 0]
    );

    DB::query(
        'INSERT IGNORE INTO settings_meta (setting_key, label, input_type, options_json, help_text, sort_order, tab_name)
         VALUES (?,?,?,?,?,?,?)',
        ['app.debug_mode', 'Debug Mode', 'checkbox', null, 'When enabled, request GET/POST and routing info is logged to #log.', 11, 'app']
    );
}

function _ensureAiUserInfra(): void
{
    DB::query(
        'INSERT IGNORE INTO roles (role_key, name, description) VALUES (?, ?, ?)',
        ['ai', 'AI User', 'Provider-backed bot identity for DM automation.']
    );

    DB::query(
        "CREATE TABLE IF NOT EXISTS ai_user_configs (
            user_id       INT UNSIGNED PRIMARY KEY,
            provider_key  VARCHAR(60)  NOT NULL DEFAULT 'openai_compat',
            base_url      VARCHAR(255) NOT NULL,
            api_key       TEXT,
            model_default VARCHAR(120) NOT NULL,
            persona_id    INT UNSIGNED,
            headers_json  TEXT,
            is_enabled    TINYINT(1)   NOT NULL DEFAULT 1,
            settings_json TEXT,
            updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    AiUsers::resetTableCache();
}

function _ensureDefaultPersonas(): void
{
    DB::query(
        'INSERT IGNORE INTO personas (persona_key, name, system_prompt, style_notes, is_enabled, is_default, settings_json)
         VALUES (?,?,?,?,1,1,?)',
        [
            'assistant',
            'Assistant',
            'You are a helpful, concise assistant running on a local LAN chat system. Be direct and useful. Format code in markdown code blocks.',
            'Balanced general-purpose helper.',
            Util::jsonEncode(['temperature' => 0.3, 'max_tokens' => 1200]),
        ]
    );

    foreach (_personaExamples() as $example) {
        DB::query(
            'INSERT IGNORE INTO personas (persona_key, name, system_prompt, style_notes, is_enabled, is_default, settings_json)
             VALUES (?,?,?,?,1,0,?)',
            [
                (string) ($example['persona_key'] ?? ''),
                (string) ($example['name'] ?? ''),
                (string) ($example['system_prompt'] ?? ''),
                (string) ($example['style_notes'] ?? ''),
                isset($example['settings_json']) ? Util::jsonEncode($example['settings_json']) : null,
            ]
        );
    }
}

function _personaExamples(): array
{
    return [
        'support_pro' => [
            'persona_key' => 'support-pro',
            'name' => 'Support Pro',
            'system_prompt' => 'You are a senior customer support engineer. Diagnose clearly, ask concise clarifying questions, and always provide step-by-step remediation options with likely root cause first.',
            'style_notes' => 'Calm, practical, no fluff, checklists preferred.',
            'settings_json' => ['temperature' => 0.2, 'max_tokens' => 1000],
        ],
        'php_mentor' => [
            'persona_key' => 'php-mentor',
            'name' => 'PHP Mentor',
            'system_prompt' => 'You are a pragmatic PHP mentor. Prefer simple, readable PHP 8.2 code, explain tradeoffs, and include safe defaults for security, validation, and error handling.',
            'style_notes' => 'Teaching tone with short examples and before/after snippets.',
            'settings_json' => ['temperature' => 0.35, 'max_tokens' => 1400],
        ],
        'windows_helper' => [
            'persona_key' => 'windows-helper',
            'name' => 'Windows Helper',
            'system_prompt' => 'You are a Windows Helper. Explain Windows troubleshooting in plain English with UI-first directions and minimal jargon. Assume non-technical users may be present and include easy verification checks after each step.',
            'style_notes' => 'Simple language, GUI paths first, command line only when needed.',
            'settings_json' => ['temperature' => 0.25, 'max_tokens' => 1200],
        ],
        'sysadmin_light' => [
            'persona_key' => 'sysadmin-light',
            'name' => 'Sysadmin Light',
            'system_prompt' => 'You are Sysadmin Light. Be server-minded and practical: commands first, minimal theory, clear rollback notes, and concise troubleshooting paths.',
            'style_notes' => 'Terminal-first, short rationale, safety and rollback included.',
            'settings_json' => ['temperature' => 0.2, 'max_tokens' => 1100],
        ],
        'sales_assistant' => [
            'persona_key' => 'sales-assistant',
            'name' => 'Sales Assistant',
            'system_prompt' => 'You are a Sales Assistant. Be friendly, concise, and customer-aware. Help draft polished replies, summarize leads, and identify next actions and follow-up timing.',
            'style_notes' => 'Warm tone, concise bullets, clear calls-to-action.',
            'settings_json' => ['temperature' => 0.5, 'max_tokens' => 1200],
        ],
        'log_analyst' => [
            'persona_key' => 'log-analyst',
            'name' => 'Log Analyst',
            'system_prompt' => 'You are a Log Analyst. Read logs carefully, group related errors, highlight likely causes, and propose prioritized next checks with concrete commands or queries.',
            'style_notes' => 'Pattern-oriented, severity ranking, next-check checklist.',
            'settings_json' => ['temperature' => 0.15, 'max_tokens' => 1200],
        ],
        'teacher_simple' => [
            'persona_key' => 'teacher-simple',
            'name' => 'Teacher Simple',
            'system_prompt' => 'You are Teacher Simple. Explain things slowly and clearly with low assumptions, larger step-by-step structure, and short checkpoints to confirm understanding.',
            'style_notes' => 'Beginner-friendly pacing, extra context, frequent check-ins.',
            'settings_json' => ['temperature' => 0.3, 'max_tokens' => 1400],
        ],
        'bug_hunter' => [
            'persona_key' => 'bug-hunter',
            'name' => 'Bug Hunter',
            'system_prompt' => 'You are a ruthless debugging specialist. Start from symptoms, isolate variables, reproduce quickly, then propose minimal high-confidence fixes and verification steps.',
            'style_notes' => 'Direct and investigative. Prioritize likely causes by probability.',
            'settings_json' => ['temperature' => 0.15, 'max_tokens' => 900],
        ],
        'release_manager' => [
            'persona_key' => 'release-manager',
            'name' => 'Release Manager',
            'system_prompt' => 'You manage release readiness. Enforce risk checks, rollback plans, migration safety, and release notes quality. Highlight blockers and go/no-go decisions.',
            'style_notes' => 'Structured, checklist-heavy, risk-first communication.',
            'settings_json' => ['temperature' => 0.25, 'max_tokens' => 1200],
        ],
        'docs_writer' => [
            'persona_key' => 'docs-writer',
            'name' => 'Docs Writer',
            'system_prompt' => 'You are a technical documentation specialist. Convert implementation details into crisp docs with quick-start, prerequisites, examples, and troubleshooting sections.',
            'style_notes' => 'Readable, skimmable, headings + examples + caveats.',
            'settings_json' => ['temperature' => 0.45, 'max_tokens' => 1600],
        ],
        'security_guard' => [
            'persona_key' => 'security-guard',
            'name' => 'Security Guard',
            'system_prompt' => 'You are an application security reviewer. Identify input validation gaps, auth/authz weaknesses, sensitive data exposure, and recommend practical mitigations.',
            'style_notes' => 'Defensive mindset, severity labels, concise mitigations.',
            'settings_json' => ['temperature' => 0.2, 'max_tokens' => 1100],
        ],
        'product_copilot' => [
            'persona_key' => 'product-copilot',
            'name' => 'Product Copilot',
            'system_prompt' => 'You are a product-thinking assistant for engineering teams. Translate requests into scope, user impact, edge cases, and measurable acceptance criteria.',
            'style_notes' => 'Outcome-focused, clear assumptions, concise specs.',
            'settings_json' => ['temperature' => 0.5, 'max_tokens' => 1300],
        ],
        'creative_brainstorm' => [
            'persona_key' => 'creative-brainstorm',
            'name' => 'Creative Brainstorm',
            'system_prompt' => 'You are a creative ideation partner. Generate diverse, actionable ideas with pros/cons, bold options, and fast experiments to validate each direction.',
            'style_notes' => 'Energetic and varied, but always actionable.',
            'settings_json' => ['temperature' => 0.8, 'max_tokens' => 1400],
        ],
    ];
}

function _buildDmRoomMeta(array $rooms): array
{
    $dmRoomIds = [];
    foreach ($rooms as $room) {
        if (($room['room_type'] ?? '') === 'dm') {
            $dmRoomIds[] = (int) $room['id'];
        }
    }
    if (!$dmRoomIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($dmRoomIds), '?'));
    $rows = DB::fetchAll(
        "SELECT rp.room_id, rp.participant_type, rp.participant_id,
                u.display_name AS user_name,
                p.name AS persona_name
         FROM room_participants rp
         LEFT JOIN users u
           ON rp.participant_type = 'user' AND u.id = rp.participant_id
         LEFT JOIN personas p
           ON rp.participant_type = 'persona' AND p.id = rp.participant_id
         WHERE rp.room_id IN ($placeholders)",
        $dmRoomIds
    );

    $out = [];
    foreach ($dmRoomIds as $id) {
        $out[$id] = ['label' => 'DM', 'detail' => ''];
    }

    $usersByRoom    = [];
    $personasByRoom = [];
    foreach ($rows as $row) {
        $rid = (int) $row['room_id'];
        if ($row['participant_type'] === 'user') {
            $usersByRoom[$rid][] = [
                'id'   => (int) $row['participant_id'],
                'name' => (string) ($row['user_name'] ?? ('User ' . $row['participant_id'])),
            ];
        }
        if ($row['participant_type'] === 'persona') {
            $personasByRoom[$rid][] = (string) ($row['persona_name'] ?? ('Persona ' . $row['participant_id']));
        }
    }

    foreach ($dmRoomIds as $rid) {
        $users   = $usersByRoom[$rid] ?? [];
        $personas = $personasByRoom[$rid] ?? [];

        if ($personas) {
            $out[$rid]['label']  = 'DM AI';
            $out[$rid]['detail'] = implode(', ', array_unique($personas));
            continue;
        }
        if ($users) {
            $userIds   = array_values(array_unique(array_map(fn($u) => (int) $u['id'], $users)));
            $userNames = array_values(array_unique(array_map(fn($u) => (string) $u['name'], $users)));
            if (count($userIds) === 1) {
                $out[$rid]['label']  = 'DM Self';
                $out[$rid]['detail'] = $userNames[0] ?? '';
            } else {
                $out[$rid]['label']  = 'DM';
                $out[$rid]['detail'] = implode(' ↔ ', $userNames);
            }
        }
    }

    return $out;
}

function _detectWebhookBaseUrl(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '/ai/webhook.php?key=';
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host . '/ai/webhook.php?key=';
}
