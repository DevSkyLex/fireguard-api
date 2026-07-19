<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Adapter\Http;

use Assistant\Application\Contract\Generation\AssistantGenerationOutcome;
use Assistant\Application\Port\Outbound\AssistantGenerationClientPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function rtrim;
use function sprintf;
use function strpos;
use function substr;
use function trim;

/**
 * Adapter OllamaGenerationClientAdapter.
 *
 * Calls Ollama's `/api/chat` endpoint with `stream: true` and parses the
 * NDJSON (one JSON object per line) streamed response via
 * `symfony/http-client`'s chunked iteration. `$baseUrl` is OPERATOR
 * deployment configuration (`OLLAMA_BASE_URL`) — the same trust class as
 * `MAILER_DSN` — and is NEVER derived from any tenant/organization value;
 * unlike `Webhook\Infrastructure\Adapter\Http\SymfonyHttpWebhookClientAdapter`,
 * this adapter deliberately does NOT re-validate the resolved address
 * against an SSRF denylist: that hardening exists only where the target is
 * tenant-supplied (webhook subscription URLs), which is not the case here.
 *
 * Never throws: every failure (unreachable backend, non-2xx status, a
 * mid-stream transport error, an empty response) is reported through the
 * returned {@see AssistantGenerationOutcome}.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OllamaGenerationClientAdapter implements AssistantGenerationClientPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param HttpClientInterface $httpClient the Symfony HTTP client
   * @param string $baseUrl the Ollama server base URL (operator configuration only)
   */
  public function __construct(
    private HttpClientInterface $httpClient,
    #[Autowire('%env(OLLAMA_BASE_URL)%')]
    private string $baseUrl,
  ) {
  }
  // #endregion

  // #region Methods
  public function streamChat(string $model, array $messages, float $temperature, int $timeoutSeconds, callable $onFragment): AssistantGenerationOutcome
  {
    try {
      $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/api/chat', [
        'json' => [
          'model' => $model,
          'messages' => $messages,
          'stream' => true,
          'options' => ['temperature' => $temperature],
        ],
        'timeout' => $timeoutSeconds,
      ]);

      $statusCode = $response->getStatusCode();
    } catch (TransportExceptionInterface $exception) {
      return new AssistantGenerationOutcome('', null, 'ollama_unreachable', $exception->getMessage());
    }

    if ($statusCode < 200 || $statusCode >= 300) {
      return new AssistantGenerationOutcome('', null, 'ollama_http_error', sprintf('Unexpected HTTP status %d.', $statusCode));
    }

    $body = '';
    $tokenCount = null;
    $buffer = '';

    try {
      foreach ($this->httpClient->stream($response) as $chunk) {
        if ($chunk->isTimeout()) {
          return new AssistantGenerationOutcome($body, $tokenCount, 'ollama_timeout', 'Timed out waiting for a response fragment.');
        }

        $buffer .= $chunk->getContent();

        while (false !== ($newlinePosition = strpos($buffer, "\n"))) {
          $line = trim(substr($buffer, 0, $newlinePosition));
          $buffer = substr($buffer, $newlinePosition + 1);

          if ('' === $line) {
            continue;
          }

          $decoded = json_decode($line, true);
          if (!is_array($decoded)) {
            continue;
          }

          // Ollama's `/api/chat` carries the fragment at `message.content`.
          // (`/api/generate` uses a flat `response` field instead — reading the
          // wrong one yields a silently empty answer, so the shape is asserted
          // rather than assumed.)
          $message = $decoded['message'] ?? null;
          $delta = is_array($message) ? ($message['content'] ?? null) : null;
          if (is_string($delta) && '' !== $delta) {
            $body .= $delta;
            $onFragment($body);
          }

          if (true === ($decoded['done'] ?? false)) {
            $tokenCount = is_int($decoded['eval_count'] ?? null) ? $decoded['eval_count'] : $tokenCount;
          }
        }
      }
    } catch (TransportExceptionInterface $exception) {
      return new AssistantGenerationOutcome($body, $tokenCount, 'ollama_stream_error', $exception->getMessage());
    }

    if ('' === $body) {
      return new AssistantGenerationOutcome('', null, 'ollama_empty_response', 'The model returned an empty response.');
    }

    return new AssistantGenerationOutcome($body, $tokenCount);
  }
  // #endregion
}
