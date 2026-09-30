<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\{Operation, Response};
use Auth\Presentation\Api\Resource\AuthResource;
use PHPUnit\Framework\Attributes\{CoversNothing, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Shared\Presentation\Api\EventSubscriber\RateLimitFailureSubscriber;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response as HttpResponse};
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function array_keys;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Test AuthResourceTest.
 *
 * @category Resource Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversNothing]
final class AuthResourceTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testResourceCanBeInstantiated(): void
  {
    self::assertInstanceOf(AuthResource::class, new AuthResource());
  }

  #[Test]
  #[DataProvider('errorResponses')]
  public function testErrorResponsesExposeTheirTransportSchema(string $operationName, int $status): void
  {
    $content = $this->errorResponse($operationName, $status)->getContent();
    self::assertNotNull($content);
    $mediaTypes = $content->getArrayCopy();
    foreach ([
      'application/ld+json' => '#/components/schemas/Error.jsonld',
      'application/problem+json' => '#/components/schemas/Error',
      'application/json' => '#/components/schemas/Error',
    ] as $mediaType => $reference) {
      self::assertArrayHasKey($mediaType, $mediaTypes);
      $media = $mediaTypes[$mediaType];
      self::assertIsArray($media);
      self::assertArrayHasKey('schema', $media);
      $schema = $media['schema'];
      self::assertIsArray($schema);
      self::assertArrayHasKey('$ref', $schema);
      self::assertSame($reference, $schema['$ref']);
    }
  }

  #[Test]
  #[DataProvider('rateLimitResponses')]
  public function testRateLimitResponsesDescribeSubscriberTransport(string $operationName, string $accept, ?int $retryDelay): void
  {
    $request = new Request();
    $request->headers->set('Accept', $accept);
    $event = new ExceptionEvent(
      kernel: $this->createStub(HttpKernelInterface::class),
      request: $request,
      requestType: HttpKernelInterface::MAIN_REQUEST,
      e: new HttpException(HttpResponse::HTTP_TOO_MANY_REQUESTS, 'Please retry', null, null === $retryDelay ? [] : ['Retry-After' => (string) $retryDelay]),
    );
    new RateLimitFailureSubscriber()->onException($event);

    $response = $event->getResponse();
    self::assertInstanceOf(JsonResponse::class, $response);
    self::assertTrue($event->isPropagationStopped());
    self::assertSame(HttpResponse::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
    $mediaType = $response->headers->get('Content-Type');
    self::assertNotNull($mediaType);
    $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    self::assertSame($retryDelay, $body['retryAfterSeconds']);

    $content = $this->errorResponse($operationName, $response->getStatusCode())->getContent();
    self::assertNotNull($content);
    $mediaTypes = $content->getArrayCopy();
    self::assertSame([$mediaType], array_keys($mediaTypes));
    $media = $mediaTypes[$mediaType];
    self::assertIsArray($media);
    $schema = $media['schema'];
    self::assertIsArray($schema);
    $components = $schema['allOf'];
    self::assertIsArray($components);
    self::assertSame(['$ref' => '#/components/schemas/Error'], $components[0]);
    $extensions = $components[1];
    self::assertIsArray($extensions);
    self::assertSame('object', $extensions['type']);
    self::assertSame(['code', 'retryAfterSeconds'], $extensions['required']);
    $properties = $extensions['properties'];
    self::assertIsArray($properties);
    $code = $properties['code'];
    self::assertIsArray($code);
    self::assertSame('string', $code['type']);
    self::assertSame([$body['code']], $code['enum']);
    $delay = $properties['retryAfterSeconds'];
    self::assertIsArray($delay);
    self::assertSame(['integer', 'null'], $delay['type']);
    self::assertSame(0, $delay['minimum']);
  }

  /**
   * @return iterable<string, array{string, int}>
   */
  public static function errorResponses(): iterable
  {
    foreach (['login', 'refresh', 'mfa_verify', 'mfa_resend'] as $operation) {
      yield $operation . ' 401' => [$operation, HttpResponse::HTTP_UNAUTHORIZED];
    }
    yield 'mfa_resend 404' => ['mfa_resend', HttpResponse::HTTP_NOT_FOUND];
  }

  /**
   * @return iterable<string, array{string, string, int|null}>
   */
  public static function rateLimitResponses(): iterable
  {
    foreach (['login', 'refresh', 'mfa_verify', 'mfa_resend'] as $operation) {
      foreach (['application/ld+json', 'application/json', 'application/problem+json'] as $accept) {
        foreach ([42, null] as $retryDelay) {
          yield $operation . ' ' . $accept . ' ' . ($retryDelay ?? 'unknown') => [$operation, $accept, $retryDelay];
        }
      }
    }
  }

  private function errorResponse(string $operationName, int $status): Response
  {
    $attribute = new ReflectionClass(AuthResource::class)->getAttributes(ApiResource::class)[0];
    $resource = $attribute->newInstance();
    $operations = $resource->getOperations();
    self::assertNotNull($operations);
    foreach ($operations as $operation) {
      if ($operation->getName() !== $operationName) {
        continue;
      }
      $openapi = $operation->getOpenapi();
      self::assertInstanceOf(Operation::class, $openapi);
      $responses = $openapi->getResponses();
      self::assertNotNull($responses);
      self::assertArrayHasKey($status, $responses);
      $response = $responses[$status];
      self::assertInstanceOf(Response::class, $response);

      return $response;
    }
    self::fail('Missing documented authentication operation: ' . $operationName);
  }

  // #endregion
}
