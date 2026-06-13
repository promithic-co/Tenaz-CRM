<?php

namespace App\Ai\Tools;

use App\Models\ToolDefinition;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GenericWebhookTool implements Tool
{
    public function __construct(private readonly ToolDefinition $definition) {}

    public function description(): Stringable|string
    {
        return $this->definition->description;
    }

    public function schema(JsonSchema $schema): array
    {
        $customSchema = $this->definition->schema;
        if (! empty($customSchema)) {
            return $customSchema;
        }

        // Default: accept any JSON object
        return $schema->object(
            properties: [],
            required: [],
        );
    }

    public function handle(Request $request): Stringable|string
    {
        $config = $this->definition->config ?? [];
        $url = $this->resolveUrl($config['url'] ?? '');
        $method = strtolower($config['method'] ?? 'post');
        $timeout = (int) ($config['timeout'] ?? 15);
        $headers = $config['headers'] ?? [];

        if (empty($url)) {
            return json_encode(['error' => 'Tool URL not configured']);
        }

        try {
            $http = Http::timeout($timeout)->withHeaders($headers);

            $response = match ($method) {
                'get' => $http->get($url, $request->arguments()),
                'put' => $http->put($url, $request->arguments()),
                default => $http->post($url, $request->arguments()),
            };

            $data = $response->json() ?? ['status' => $response->status()];

            if (isset($config['response_mapping'])) {
                $data = $this->applyResponseMapping($data, $config['response_mapping']);
            }

            return json_encode($data);
        } catch (\Throwable $e) {
            Log::warning("GenericWebhookTool [{$this->definition->slug}] failed", [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);

            return json_encode(['error' => 'Tool call failed: '.$e->getMessage()]);
        }
    }

    /**
     * Resolve URL, substituting environment variables like {{CREDFLOW_WEBHOOK_CONSULTA}}.
     */
    /**
     * Resolve URL, substituting {{VAR}} placeholders from config/credflow.php.
     *
     * To expose a webhook URL variable, add it to config/credflow.php:
     *   'webhook_consulta' => env('WEBHOOK_CONSULTA'),
     * Then reference it in the tool URL as {{WEBHOOK_CONSULTA}}.
     *
     * env() is intentionally NOT used as a fallback — it is unreliable under
     * `php artisan config:cache`. Unresolved placeholders are left as-is so
     * the HTTP call fails visibly rather than using a null/stale value.
     */
    private function resolveUrl(string $url): string
    {
        return preg_replace_callback('/\{\{([A-Z_]+)\}\}/', function ($matches) {
            return config('credflow.'.strtolower($matches[1])) ?? $matches[0];
        }, $url);
    }

    /**
     * Apply simple dot-notation response mapping.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $mapping  e.g. ['status' => 'status', 'result' => 'data.result']
     * @return array<string, mixed>
     */
    private function applyResponseMapping(array $data, array $mapping): array
    {
        $result = [];
        foreach ($mapping as $outputKey => $path) {
            $result[$outputKey] = data_get($data, $path);
        }

        return $result;
    }
}
