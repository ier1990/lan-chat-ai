<?php
/**
 * AiProvider — OpenAI-compatible chat adapter.
 *
 * Works with OpenAI, Ollama (/v1), LM Studio, and any other /v1/chat/completions endpoint.
 *
 * NOTE: API keys are stored in the database, which is appropriate for a local LAN
 * deployment. For a public-facing instance, consider encrypting the api_key column.
 */
class AiProvider
{
    private array $provider;
    private array $model;

    public function __construct(array $provider, array $model)
    {
        $this->provider = $provider;
        $this->model    = $model;
    }

    /**
     * Load the highest-priority enabled provider and its default model.
     * Returns null if no provider/model is configured.
     */
    public static function getActive(): ?self
    {
        $provider = DB::fetch(
            'SELECT * FROM ai_providers WHERE is_enabled = 1 ORDER BY priority DESC LIMIT 1'
        );
        if (!$provider) {
            return null;
        }

        $model = DB::fetch(
            'SELECT * FROM ai_models WHERE provider_id = ? AND model_key = ? AND is_enabled = 1',
            [$provider['id'], $provider['model_default']]
        );
        if (!$model) {
            // Fall back to any enabled model for this provider.
            $model = DB::fetch(
                'SELECT * FROM ai_models WHERE provider_id = ? AND is_enabled = 1 LIMIT 1',
                [$provider['id']]
            );
        }
        if (!$model) {
            return null;
        }

        return new self($provider, $model);
    }

    /**
     * Fetch available model IDs from an OpenAI-compatible /models endpoint.
     *
     * @return array<string> model keys
     */
    public static function fetchModelKeys(array $provider): array
    {
        $url    = rtrim((string) $provider['base_url'], '/') . '/models';
        $apiKey = (string) ($provider['api_key'] ?? '');

        $headers = ['Content-Type: application/json'];
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new RuntimeException('Model listing failed: ' . $curlErr);
        }
        if ($httpCode !== 200) {
            throw new RuntimeException('Model listing returned HTTP ' . $httpCode . ': ' . $response);
        }

        $data = json_decode((string) $response, true);
        if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
            throw new RuntimeException('Provider /models response format is invalid.');
        }

        $keys = [];
        foreach ($data['data'] as $item) {
            $id = isset($item['id']) ? trim((string) $item['id']) : '';
            if ($id !== '') {
                $keys[] = $id;
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);
        return $keys;
    }

    /**
     * Sync discovered model IDs into ai_models for this provider.
     *
     * @return array{added:int,total:int,default_model:string|null}
     */
    public static function syncProviderModels(array $provider): array
    {
        $providerId = (int) $provider['id'];
        $keys       = self::fetchModelKeys($provider);
        $added      = 0;

        foreach ($keys as $key) {
            $exists = DB::fetchColumn(
                'SELECT id FROM ai_models WHERE provider_id = ? AND model_key = ?',
                [$providerId, $key]
            );
            if ($exists) {
                DB::update('ai_models', ['is_enabled' => 1], 'id = ?', [(int) $exists]);
                continue;
            }

            DB::insert('ai_models', [
                'provider_id'         => $providerId,
                'model_key'           => $key,
                'label'               => $key,
                'context_window'      => null,
                'max_tokens'          => 2048,
                'temperature_default' => 0.70,
                'is_enabled'          => 1,
                'supports_tools'      => 0,
                'supports_images'     => 0,
                'supports_reasoning'  => 0,
            ]);
            $added++;
        }

        $defaultModel = null;
        if (empty($provider['model_default']) && !empty($keys)) {
            $defaultModel = $keys[0];
            DB::update('ai_providers', ['model_default' => $defaultModel], 'id = ?', [$providerId]);
        }

        return [
            'added'         => $added,
            'total'         => count($keys),
            'default_model' => $defaultModel,
        ];
    }

    /**
     * Send a chat/completions request.
     *
     * @param array  $messages  OpenAI-format messages: [['role'=>'user','content'=>'...'], ...]
     * @param array  $options   Optional overrides: temperature, max_tokens
     * @return array            ['text', 'model', 'tokens_in', 'tokens_out']
     * @throws RuntimeException on network or API errors
     */
    public function chat(array $messages, array $options = []): array
    {
        $url     = rtrim($this->provider['base_url'], '/') . '/chat/completions';
        $apiKey  = $this->provider['api_key'] ?? '';
        $payload = [
            'model'       => $this->model['model_key'],
            'messages'    => $messages,
            'temperature' => (float) ($options['temperature'] ?? $this->model['temperature_default'] ?? 0.7),
            'max_tokens'  => (int)   ($options['max_tokens']  ?? $this->model['max_tokens'] ?? 2048),
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new RuntimeException('Provider request failed: ' . $curlErr);
        }
        if ($httpCode !== 200) {
            throw new RuntimeException('Provider returned HTTP ' . $httpCode . ': ' . $response);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON from provider');
        }

        return [
            'text'       => trim($data['choices'][0]['message']['content'] ?? ''),
            'model'      => $data['model'] ?? $this->model['model_key'],
            'tokens_in'  => (int) ($data['usage']['prompt_tokens']     ?? 0),
            'tokens_out' => (int) ($data['usage']['completion_tokens']  ?? 0),
        ];
    }

    public function providerKey(): string { return $this->provider['provider_key']; }
    public function modelKey(): string    { return $this->model['model_key']; }
    public function providerId(): int     { return (int) $this->provider['id']; }
    public function modelId(): int        { return (int) $this->model['id']; }
}
