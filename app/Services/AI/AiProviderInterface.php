<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * AI Provider Interface
 * Kontrak standar integrasi model AI / LLM untuk generasi soal dan konten edukasi.
 */
interface AiProviderInterface
{
    /**
     * Kirim prompt ke AI dan dapatkan respon terstruktur
     *
     * @param string $prompt Instruksi dan konteks untuk AI
     * @param array $options Opsi tambahan (temperature, max_tokens, response_format, system_instruction)
     * @return array{success: bool, content?: string, raw?: mixed, usage?: array, error?: string}
     */
    public function generateCompletion(string $prompt, array $options = []): array;

    /**
     * Periksa apakah provider memiliki konfigurasi API key yang valid dan siap digunakan
     */
    public function isAvailable(): bool;

    /**
     * Dapatkan nama pengenal provider (e.g., 'openai', 'gemini', 'mock')
     */
    public function getProviderName(): string;
}
