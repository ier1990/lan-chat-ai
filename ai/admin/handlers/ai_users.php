<?php
/**
 * handlers/ai_users.php — _ai_users_action POST handler.
 * Returns [$flash, $flashType].
 */
function _handleAiUsers(): array
{
    $action       = Util::post('_ai_users_action');
    $targetUserId = (int) Util::post('user_id');

    if ($action === 'create_ai_user') {
        $username    = Util::post('username');
        $displayName = Util::post('display_name');
        $baseUrl     = Util::post('base_url');
        $modelKey    = Util::post('model_default');
        $apiKey      = Util::post('api_key');
        $providerKey = Util::post('provider_key', 'openai_compat');
        $personaId   = (int) Util::post('persona_id');
        $httpReferer = Util::post('http_referer');
        $xTitle      = Util::post('x_title');
        $headersJson = Util::post('headers_json');
        $headersData = AiUsers::decodeHeaders($headersJson);

        if ($httpReferer !== '') { $headersData['HTTP-Referer'] = $httpReferer; }
        if ($xTitle !== '')      { $headersData['X-Title']     = $xTitle; }

        if ($username === '' || $displayName === '' || $baseUrl === '' || $modelKey === '' || $apiKey === '') {
            return ['Username, display name, endpoint URL, model, and API key are required.', 'error'];
        }
        if ($headersJson !== '' && Util::jsonDecode($headersJson) === null && strtolower(trim($headersJson)) !== 'null') {
            return ['Extra headers must be valid JSON.', 'error'];
        }
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
            return ['Username may only contain letters, numbers, dot, underscore, and dash.', 'error'];
        }
        if (Users::getByUsername($username)) {
            return ['That username already exists.', 'error'];
        }

        $userId = Users::create($username, $displayName, $apiKey, 'ai');
        AiUsers::upsertConfig($userId, [
            'provider_key'  => $providerKey,
            'base_url'      => $baseUrl,
            'api_key'       => $apiKey,
            'model_default' => $modelKey,
            'persona_id'    => $personaId ?: null,
            'headers_json'  => AiUsers::encodeHeaders($headersData),
            'is_enabled'    => 1,
        ]);
        return ['AI user created.', 'success'];
    }

    if ($action === 'save_ai_user') {
        $user        = Users::getById($targetUserId);
        $baseUrl     = Util::post('base_url');
        $modelKey    = Util::post('model_default');
        $apiKey      = Util::post('api_key');
        $providerKey = Util::post('provider_key', 'openai_compat');
        $personaId   = (int) Util::post('persona_id');
        $isEnabled   = Util::post('is_enabled', '0') === '1' ? 1 : 0;
        $httpReferer = Util::post('http_referer');
        $xTitle      = Util::post('x_title');
        $headersJson = Util::post('headers_json');
        $headersData = AiUsers::decodeHeaders($headersJson);

        if ($httpReferer !== '') { $headersData['HTTP-Referer'] = $httpReferer; }
        if ($xTitle !== '')      { $headersData['X-Title']     = $xTitle; }

        if (!$user || $user['role_key'] !== 'ai') {
            return ['AI user not found.', 'error'];
        }
        if ($baseUrl === '' || $modelKey === '') {
            return ['Endpoint URL and model are required.', 'error'];
        }
        if ($headersJson !== '' && Util::jsonDecode($headersJson) === null && strtolower(trim($headersJson)) !== 'null') {
            return ['Extra headers must be valid JSON.', 'error'];
        }

        $cfgData = [
            'provider_key'  => $providerKey,
            'base_url'      => $baseUrl,
            'model_default' => $modelKey,
            'persona_id'    => $personaId ?: null,
            'headers_json'  => AiUsers::encodeHeaders($headersData),
            'is_enabled'    => $isEnabled,
        ];
        if ($apiKey !== '') {
            $cfgData['api_key'] = $apiKey;
            Users::setPassword($targetUserId, $apiKey);
        }
        AiUsers::upsertConfig($targetUserId, $cfgData);
        return ['AI user config updated.', 'success'];
    }

    return ['', 'success'];
}
