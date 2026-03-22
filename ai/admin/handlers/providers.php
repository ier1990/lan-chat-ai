<?php
/**
 * handlers/providers.php — _provider_action POST handler.
 * Returns [$flash, $flashType].
 */
function _handleProviders(): array
{
    $action = Util::post('_provider_action');

    if ($action === 'refresh_models') {
        $providerId = (int) Util::post('provider_id');
        $provider   = DB::fetch('SELECT * FROM ai_providers WHERE id = ?', [$providerId]);

        if (!$provider) {
            return ['Provider not found.', 'error'];
        }
        try {
            $sync  = AiProvider::syncProviderModels($provider);
            $flash = 'Models refreshed. Found ' . $sync['total'] . ', added ' . $sync['added'] . '.';
            if (!empty($sync['default_model'])) {
                $flash .= ' Default set to ' . $sync['default_model'] . '.';
            }
            return [$flash, 'success'];
        } catch (Throwable $e) {
            return ['Model refresh failed: ' . $e->getMessage(), 'error'];
        }
    }

    if ($action === 'set_default_model') {
        $providerId = (int) Util::post('provider_id');
        $modelKey   = Util::post('model_key');
        $provider   = DB::fetch('SELECT * FROM ai_providers WHERE id = ?', [$providerId]);

        if (!$provider) {
            return ['Provider not found.', 'error'];
        }
        $model = DB::fetch(
            'SELECT id FROM ai_models WHERE provider_id = ? AND model_key = ?',
            [$providerId, $modelKey]
        );
        if (!$model) {
            return ['Selected model does not belong to this provider.', 'error'];
        }

        DB::update('ai_providers', ['model_default' => $modelKey], 'id = ?', [$providerId]);
        return ['Default model updated.', 'success'];
    }

    if ($action === 'save_provider') {
        $providerId = (int) Util::post('provider_id');
        $baseUrl    = trim(Util::post('base_url'));
        $apiKey     = Util::post('api_key');
        $modelKey   = trim(Util::post('model_default'));
        $doRefresh  = Util::post('refresh_models', '0') === '1';

        $provider = DB::fetch('SELECT * FROM ai_providers WHERE id = ?', [$providerId]);
        if (!$provider) {
            return ['Provider not found.', 'error'];
        }
        if ($baseUrl === '') {
            return ['Endpoint URL is required.', 'error'];
        }

        $update = ['base_url' => $baseUrl];
        if ($apiKey !== '') {
            $update['api_key'] = $apiKey;
        }
        if ($modelKey !== '') {
            $update['model_default'] = $modelKey;
        }
        DB::update('ai_providers', $update, 'id = ?', [$providerId]);

        $provider = DB::fetch('SELECT * FROM ai_providers WHERE id = ?', [$providerId]);

        if ($doRefresh) {
            try {
                $sync  = AiProvider::syncProviderModels($provider);
                $flash = 'Saved. ' . $sync['total'] . ' model(s) found, ' . $sync['added'] . ' new.';
                if ($sync['default_model']) {
                    $flash .= ' Auto-selected: ' . $sync['default_model'] . '.';
                }
                return [$flash, 'success'];
            } catch (Throwable $e) {
                return ['Saved, but model fetch failed: ' . $e->getMessage(), 'error'];
            }
        }

        return ['Provider saved.', 'success'];
    }

    return ['', 'success'];
}
