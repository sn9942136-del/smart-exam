<?php

declare(strict_types=1);

namespace App\Services\AI;

use Throwable;

/**
 * OpenAI Provider
 * Integrasi OpenAI Chat Completions API (GPT-4o, GPT-4o-mini, GPT-3.5-turbo)
 * dengan response format JSON terstruktur.
 */
class OpenAiProvider implements AiProviderInterface
{
    private ?string $apiKey;
    private string $model;
    private string $apiBaseUrl;

    public function __construct(?string $apiKey = null, ?string $model = null, ?string $apiBaseUrl = null)
    {
        $this->apiKey = $apiKey 
            ?? (function_exists('system_setting') ? (system_setting('openai_api_key') ?: null) : null)
            ?? $_ENV['OPENAI_API_KEY'] 
            ?? $_ENV['AI_API_KEY'] 
            ?? getenv('OPENAI_API_KEY') 
            ?? getenv('AI_API_KEY') 
            ?: null;

        $this->model = $model 
            ?? (function_exists('system_setting') ? (system_setting('ai_question_generation_model') ?: null) : null)
            ?? $_ENV['OPENAI_MODEL'] 
            ?? $_ENV['AI_MODEL'] 
            ?? 'gpt-4o-mini';

        $this->apiBaseUrl = rtrim($apiBaseUrl ?? $_ENV['OPENAI_BASE_URL'] ?? 'https://api.openai.com/v1', '/');
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function generateCompletion(string $prompt, array $options = []): array
    {
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'error'   => 'OpenAI API Key tidak dikonfigurasi di environment variable (OPENAI_API_KEY / AI_API_KEY).'
            ];
        }

        $systemPrompt = $options['system_instruction'] ?? 'You are an expert Indonesian educational curriculum assessment AI. Always respond strictly in valid JSON format.';
        $temperature = (float)($options['temperature'] ?? 0.7);
        $maxTokens = (int)($options['max_tokens'] ?? 3500);

        $payload = [
            'model'           => $this->model,
            'messages'        => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt]
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature'     => $temperature,
            'max_tokens'      => $maxTokens
        ];

        try {
            $endpoint = "{$this->apiBaseUrl}/chat/completions";
            $ch = curl_init($endpoint);

            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ];

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return [
                    'success' => false,
                    'error'   => "Gagal menghubungi OpenAI: {$curlError}"
                ];
            }

            if ($httpCode !== 200) {
                $decodedError = json_decode((string)$responseBody, true);
                $msg = $decodedError['error']['message'] ?? "HTTP Status {$httpCode}";
                return [
                    'success' => false,
                    'error'   => "OpenAI Error ({$httpCode}): {$msg}"
                ];
            }

            $result = json_decode((string)$responseBody, true);
            $content = $result['choices'][0]['message']['content'] ?? '';

            return [
                'success' => true,
                'content' => $content,
                'raw'     => $result,
                'usage'   => $result['usage'] ?? []
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error'   => 'Exception saat memanggil OpenAI API: ' . $e->getMessage()
            ];
        }
    }
}
