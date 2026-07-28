<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Infrastructure\Adapter\Http;

use Assistant\Infrastructure\Adapter\Http\OllamaGenerationClientAdapter;
use Generator;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function json_encode;

/**
 * Test OllamaGenerationClientAdapterTest.
 *
 * Exercises the adapter against `symfony/http-client`'s `MockHttpClient`/
 * `MockResponse` test doubles — no test here requires a running Ollama.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OllamaGenerationClientAdapter::class)]
final class OllamaGenerationClientAdapterTest extends TestCase
{
  #[Test]
  public function testStreamChatAssemblesTheFullBodyFromFragmentsAndReportsTheTokenCount(): void
  {
    $chunks = [
      json_encode(['message' => ['content' => 'Hel'], 'done' => false]) . "\n",
      json_encode(['message' => ['content' => 'lo,'], 'done' => false]) . "\n",
      json_encode(['message' => ['content' => ' world.'], 'done' => false]) . "\n",
      json_encode(['message' => ['content' => ''], 'done' => true, 'eval_count' => 7]) . "\n",
    ];

    $httpClient = new MockHttpClient(new MockResponse($chunks, ['http_code' => 200]));
    $adapter = new OllamaGenerationClientAdapter($httpClient, 'http://localhost:11434');

    $fragments = [];
    $outcome = $adapter->streamChat(
      'llama3',
      [['role' => 'user', 'content' => 'Hi']],
      0.7,
      10,
      static function (string $accumulated) use (&$fragments): void {
        $fragments[] = $accumulated;
      },
    );

    self::assertTrue($outcome->isSuccessful());
    self::assertSame('Hello, world.', $outcome->body);
    self::assertSame(7, $outcome->tokenCount);
    // Every fragment carries the FULL accumulated body so far, never a delta.
    self::assertSame(['Hel', 'Hello,', 'Hello, world.'], $fragments);
  }

  #[Test]
  public function testStreamChatReportsUnreachableWhenTheTransportFails(): void
  {
    $httpClient = new MockHttpClient(new MockResponse('', ['error' => 'Connection refused']));
    $adapter = new OllamaGenerationClientAdapter($httpClient, 'http://localhost:11434');

    $outcome = $adapter->streamChat('llama3', [], 0.7, 10, static function (): void {
    });

    self::assertFalse($outcome->isSuccessful());
    self::assertSame('ollama_unreachable', $outcome->errorCode);
  }

  #[Test]
  public function testStreamChatReportsAnHttpErrorOnANon2xxStatus(): void
  {
    $httpClient = new MockHttpClient(new MockResponse('{}', ['http_code' => 500]));
    $adapter = new OllamaGenerationClientAdapter($httpClient, 'http://localhost:11434');

    $outcome = $adapter->streamChat('llama3', [], 0.7, 10, static function (): void {
    });

    self::assertFalse($outcome->isSuccessful());
    self::assertSame('ollama_http_error', $outcome->errorCode);
  }

  #[Test]
  public function testStreamChatReportsATimeoutOnAnIdleChunk(): void
  {
    // An empty-string chunk simulates an idle timeout (MockResponse contract).
    $httpClient = new MockHttpClient(new MockResponse([
      json_encode(['message' => ['content' => 'Hel'], 'done' => false]) . "\n",
      '',
    ], ['http_code' => 200]));
    $adapter = new OllamaGenerationClientAdapter($httpClient, 'http://localhost:11434');

    $outcome = $adapter->streamChat('llama3', [], 0.7, 10, static function (): void {
    });

    self::assertFalse($outcome->isSuccessful());
    self::assertSame('ollama_timeout', $outcome->errorCode);
  }

  #[Test]
  public function testStreamChatSkipsBlankAndNonJsonLines(): void
  {
    $httpClient = new MockHttpClient(new MockResponse([
      "\n",
      "   \n",
      "not-json-at-all\n",
      // Valid JSON, but a scalar rather than an object: still not an array.
      "\"just-a-string\"\n",
      json_encode(['message' => ['content' => 'Bonjour'], 'done' => true, 'eval_count' => 3]) . "\n",
    ], ['http_code' => 200]));
    $adapter = new OllamaGenerationClientAdapter($httpClient, 'http://localhost:11434');

    $outcome = $adapter->streamChat('llama3', [], 0.7, 10, static function (): void {
    });

    self::assertTrue($outcome->isSuccessful());
    self::assertSame('Bonjour', $outcome->body);
    self::assertSame(3, $outcome->tokenCount);
  }

  #[Test]
  public function testStreamChatReportsAStreamErrorWhenTheTransportBreaksMidStream(): void
  {
    $chunks = static function (): Generator {
      yield json_encode(['message' => ['content' => 'Par'], 'done' => false]) . "\n";

      throw new TransportException('Connection reset by peer.');
    };

    $httpClient = new MockHttpClient(new MockResponse($chunks(), ['http_code' => 200]));
    $adapter = new OllamaGenerationClientAdapter($httpClient, 'http://localhost:11434');

    $outcome = $adapter->streamChat('llama3', [], 0.7, 10, static function (): void {
    });

    self::assertFalse($outcome->isSuccessful());
    self::assertSame('ollama_stream_error', $outcome->errorCode);
    self::assertSame('Par', $outcome->body);
  }

  #[Test]
  public function testStreamChatReportsAnEmptyResponseWhenNoContentIsEverReceived(): void
  {
    $httpClient = new MockHttpClient(new MockResponse([
      json_encode(['message' => ['content' => ''], 'done' => true, 'eval_count' => 0]) . "\n",
    ], ['http_code' => 200]));
    $adapter = new OllamaGenerationClientAdapter($httpClient, 'http://localhost:11434');

    $outcome = $adapter->streamChat('llama3', [], 0.7, 10, static function (): void {
    });

    self::assertFalse($outcome->isSuccessful());
    self::assertSame('ollama_empty_response', $outcome->errorCode);
  }
}
