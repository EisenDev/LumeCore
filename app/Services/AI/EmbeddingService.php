<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    protected string $apiKey;
    protected string $model = 'text-embedding-004'; // Use latest supported model

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
    }

    /**
     * Generate an embedding vector for a given text.
     *
     * @param string $text The text to embed
     * @return array|null The embedding vector (1536 dimensions) or null on failure
     */
    public function embed(string $text): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('EmbeddingService: Gemini API key not configured.');
            return null;
        }

        // Truncate text to model limit (roughly 8k tokens, ~32k chars for safety)
        $text = mb_substr($text, 0, 32000);

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:embedContent?key={$this->apiKey}";

        try {
            $response = Http::timeout(30)->post($url, [
                'content' => [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ]);

            if ($response->failed()) {
                Log::error('EmbeddingService: API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            return $data['embedding']['values'] ?? null;

        } catch (\Exception $e) {
            Log::error('EmbeddingService: Exception during embedding', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Chunk a large text into smaller pieces suitable for embedding.
     *
     * @param string $text The text to chunk
     * @param int $chunkSize Target size per chunk (in characters)
     * @param int $overlap Overlap between chunks to maintain context
     * @return array Array of text chunks
     */
    public function chunkText(string $text, int $chunkSize = 2000, int $overlap = 200): array
    {
        $chunks = [];
        $length = mb_strlen($text);
        $start = 0;

        while ($start < $length) {
            $chunk = mb_substr($text, $start, $chunkSize);
            
            // Try to break at a sentence boundary
            if ($start + $chunkSize < $length) {
                $lastPeriod = mb_strrpos($chunk, '.');
                $lastNewline = mb_strrpos($chunk, "\n");
                $breakPoint = max($lastPeriod, $lastNewline);
                
                if ($breakPoint !== false && $breakPoint > $chunkSize / 2) {
                    $chunk = mb_substr($chunk, 0, $breakPoint + 1);
                }
            }
            
            $chunks[] = trim($chunk);
            $start += mb_strlen($chunk) - $overlap;
            
            // Safety check to prevent infinite loop
            if (mb_strlen($chunk) === 0) {
                break;
            }
        }

        return array_filter($chunks);
    }

    /**
     * Embed multiple chunks and return an array of [content, embedding] pairs.
     *
     * @param array $chunks Array of text chunks
     * @return array Array of ['content' => string, 'embedding' => array]
     */
    public function embedChunks(array $chunks): array
    {
        $results = [];
        
        foreach ($chunks as $chunk) {
            $embedding = $this->embed($chunk);
            
            if ($embedding) {
                $results[] = [
                    'content' => $chunk,
                    'embedding' => $embedding
                ];
            }
            
            // Rate limiting - Gemini allows 60 RPM for embedding
            usleep(100000); // 100ms delay between requests
        }

        return $results;
    }
}
