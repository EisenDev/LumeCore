<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected ?string $apiKey;
    protected ?string $model;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', env('GEMINI_API_KEY', ''));
        $this->model = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-2.0-flash'));
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    }

    /**
     * Generate content using Google Gemini API
     * 
     * @param array $contents Formatted contents for the API
     * @param array $options Optional generation config overrides (temperature, etc)
     * @return array Response data
     * @throws \Exception
     */
    public function generateContent(array $contents, array $options = []): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('GEMINI_API_KEY is not set in .env');
        }

        $url = "{$this->baseUrl}{$this->model}:generateContent?key={$this->apiKey}";

        // The API expects "contents" array wrapping the parts
        
        $payload = [
            'contents' => $contents
        ];

        // Default Config
        $defaultConfig = [
            'temperature' => 0.7,
            'maxOutputTokens' => 8192,
            'responseMimeType' => 'application/json',
        ];

        // Merge defaults with passed options
        $payload['generationConfig'] = array_merge($defaultConfig, $options);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])
        ->timeout(120)
        ->retry(3, 100)
        ->post($url, $payload);

        if ($response->failed()) {
            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            throw new \Exception('Gemini API Request Failed: ' . $response->body());
        }

        return $response->json();
    }
}
