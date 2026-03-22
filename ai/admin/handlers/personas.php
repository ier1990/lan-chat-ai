<?php
/**
 * handlers/personas.php — _persona_action POST handler + persona export.
 * Returns [$flash, $flashType].
 */
function _handlePersonas(): array
{
    $action    = Util::post('_persona_action');
    $personaId = (int) Util::post('persona_id');

    if ($action === 'create_persona' || $action === 'save_persona') {
        $personaKey   = strtolower(Util::post('persona_key'));
        $name         = Util::post('name');
        $systemPrompt = trim((string) ($_POST['system_prompt'] ?? ''));
        $styleNotes   = trim((string) ($_POST['style_notes'] ?? ''));
        $settingsJson = trim((string) ($_POST['settings_json'] ?? ''));
        $isEnabled    = Util::post('is_enabled', '0') === '1' ? 1 : 0;
        $isDefault    = Util::post('is_default', '0') === '1' ? 1 : 0;

        if ($personaKey === '' || $name === '' || $systemPrompt === '') {
            return ['Persona key, name, and system prompt are required.', 'error'];
        }
        if (!preg_match('/^[a-z0-9][a-z0-9_.-]*$/', $personaKey)) {
            return ['Persona key must start with a letter/number and use only lowercase letters, numbers, dot, underscore, or dash.', 'error'];
        }
        if ($settingsJson !== '' && Util::jsonDecode($settingsJson) === null && strtolower($settingsJson) !== 'null') {
            return ['Settings JSON must be valid JSON.', 'error'];
        }

        if ($action === 'create_persona') {
            if (Personas::getByKey($personaKey)) {
                return ['That persona key already exists.', 'error'];
            }
            if ($isDefault) {
                DB::query('UPDATE personas SET is_default = 0 WHERE is_default = 1');
            }
            DB::insert('personas', [
                'persona_key'   => $personaKey,
                'name'          => $name,
                'system_prompt' => $systemPrompt,
                'style_notes'   => $styleNotes !== '' ? $styleNotes : null,
                'is_enabled'    => $isEnabled,
                'is_default'    => $isDefault,
                'settings_json' => $settingsJson !== '' ? $settingsJson : null,
                'updated_at'    => Util::now(),
            ]);
            return ['Persona created.', 'success'];
        }

        // save_persona
        $existing = Personas::getById($personaId);
        if (!$existing) {
            return ['Persona not found.', 'error'];
        }
        $conflict = DB::fetch(
            'SELECT id FROM personas WHERE persona_key = ? AND id <> ?',
            [$personaKey, $personaId]
        );
        if ($conflict) {
            return ['That persona key is already in use.', 'error'];
        }
        if ($isDefault) {
            DB::query('UPDATE personas SET is_default = 0 WHERE is_default = 1 AND id <> ?', [$personaId]);
        }
        DB::update('personas', [
            'persona_key'   => $personaKey,
            'name'          => $name,
            'system_prompt' => $systemPrompt,
            'style_notes'   => $styleNotes !== '' ? $styleNotes : null,
            'is_enabled'    => $isEnabled,
            'is_default'    => $isDefault,
            'settings_json' => $settingsJson !== '' ? $settingsJson : null,
            'updated_at'    => Util::now(),
        ], 'id = ?', [$personaId]);
        return ['Persona updated.', 'success'];
    }

    if ($action === 'delete_persona') {
        $existing = Personas::getById($personaId);
        if (!$existing) {
            return ['Persona not found.', 'error'];
        }
        DB::query('DELETE FROM personas WHERE id = ?', [$personaId]);
        return ['Persona deleted.', 'success'];
    }

    if ($action === 'create_persona_example') {
        $exampleKey = Util::post('example_key');
        $examples   = _personaExamples();
        $example    = $examples[$exampleKey] ?? null;

        if (!$example) {
            return ['Example not found.', 'error'];
        }

        $baseKey      = strtolower((string) $example['persona_key']);
        $candidateKey = $baseKey;
        $n = 2;
        while (Personas::getByKey($candidateKey)) {
            $candidateKey = $baseKey . '-' . $n++;
        }

        DB::insert('personas', [
            'persona_key'   => $candidateKey,
            'name'          => (string) $example['name'],
            'system_prompt' => (string) $example['system_prompt'],
            'style_notes'   => (string) ($example['style_notes'] ?? ''),
            'is_enabled'    => 1,
            'is_default'    => 0,
            'settings_json' => isset($example['settings_json']) ? Util::jsonEncode($example['settings_json']) : null,
        ]);
        return ['Example persona "' . $example['name'] . '" created as key ' . $candidateKey . '.', 'success'];
    }

    if ($action === 'import_personas_json') {
        $payload         = trim((string) ($_POST['import_json'] ?? ''));
        $replaceExisting = Util::post('replace_existing', '0') === '1';

        if ($payload === '') {
            return ['Import JSON is required.', 'error'];
        }
        $decoded = Util::jsonDecode($payload);
        if ($decoded === null) {
            return ['Import JSON is invalid.', 'error'];
        }
        try {
            $result = _importPersonasPayload($decoded, $replaceExisting);
            return [
                'Persona import complete. Added ' . $result['created']
                    . ', updated ' . $result['updated']
                    . ', skipped ' . $result['skipped'] . '.',
                'success',
            ];
        } catch (RuntimeException $e) {
            return ['Persona import failed: ' . $e->getMessage(), 'error'];
        }
    }

    return ['', 'success'];
}

/** Stream a JSON persona export and exit. */
function _handlePersonaExport(string $personaExport): void
{
    $exportPayload = null;
    $filename      = 'personas-export.json';

    if ($personaExport === 'all') {
        $rows          = DB::fetchAll('SELECT * FROM personas ORDER BY name ASC');
        $exportPayload = [
            'version'     => 1,
            'exported_at' => gmdate('c'),
            'personas'    => array_map('_normalizePersonaRow', $rows),
        ];
    } else {
        $persona = Personas::getById((int) $personaExport);
        if ($persona) {
            $exportPayload = _normalizePersonaRow($persona);
            $filename      = 'persona-' . $persona['persona_key'] . '.json';
        }
    }

    if ($exportPayload === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Persona export not found.';
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) . '"');
    echo json_encode($exportPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function _normalizePersonaRow(array $row): array
{
    return [
        'persona_key'   => (string) ($row['persona_key'] ?? ''),
        'name'          => (string) ($row['name'] ?? ''),
        'system_prompt' => (string) ($row['system_prompt'] ?? ''),
        'style_notes'   => $row['style_notes'] !== null ? (string) $row['style_notes'] : '',
        'is_enabled'    => !empty($row['is_enabled']) ? 1 : 0,
        'is_default'    => !empty($row['is_default']) ? 1 : 0,
        'settings_json' => Util::jsonDecode((string) ($row['settings_json'] ?? '')),
    ];
}

function _importPersonasPayload(mixed $payload, bool $replaceExisting): array
{
    if (is_array($payload) && array_key_exists('personas', $payload) && is_array($payload['personas'])) {
        $items = $payload['personas'];
    } elseif (is_array($payload) && array_is_list($payload)) {
        $items = $payload;
    } elseif (is_array($payload)) {
        $items = [$payload];
    } else {
        throw new RuntimeException('Expected a persona object or personas array.');
    }

    $created = $updated = $skipped = 0;
    $defaultPersonaId = null;

    foreach ($items as $item) {
        if (!is_array($item)) { $skipped++; continue; }

        $personaKey   = strtolower(trim((string) ($item['persona_key'] ?? '')));
        $name         = trim((string) ($item['name'] ?? ''));
        $systemPrompt = trim((string) ($item['system_prompt'] ?? ''));

        if ($personaKey === '' || $name === '' || $systemPrompt === '') { $skipped++; continue; }
        if (!preg_match('/^[a-z0-9][a-z0-9_.-]*$/', $personaKey))      { $skipped++; continue; }

        $row = [
            'persona_key'   => $personaKey,
            'name'          => $name,
            'system_prompt' => $systemPrompt,
            'style_notes'   => trim((string) ($item['style_notes'] ?? '')) ?: null,
            'is_enabled'    => !empty($item['is_enabled']) ? 1 : 0,
            'is_default'    => !empty($item['is_default']) ? 1 : 0,
            'settings_json' => array_key_exists('settings_json', $item) ? Util::jsonEncode($item['settings_json']) : null,
            'updated_at'    => Util::now(),
        ];

        $existing = Personas::getByKey($personaKey);
        if ($existing) {
            if (!$replaceExisting) { $skipped++; continue; }
            DB::update('personas', $row, 'id = ?', [(int) $existing['id']]);
            $updated++;
            if ($row['is_default']) { $defaultPersonaId = (int) $existing['id']; }
            continue;
        }

        DB::insert('personas', $row);
        $created++;
        if ($row['is_default']) {
            $defaultPersonaId = (int) DB::fetchColumn('SELECT id FROM personas WHERE persona_key = ?', [$personaKey]);
        }
    }

    if ($defaultPersonaId) {
        DB::query('UPDATE personas SET is_default = 0 WHERE id <> ?', [$defaultPersonaId]);
        DB::query('UPDATE personas SET is_default = 1 WHERE id = ?', [$defaultPersonaId]);
    }

    return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
}
