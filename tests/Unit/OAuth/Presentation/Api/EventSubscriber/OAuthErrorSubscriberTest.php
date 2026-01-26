<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\EventSubscriber;

use League\OAuth2\Server\Exception\OAuthServerException;
use OAuth\Domain\Exception\Client\{InvalidClientException, InvalidOAuthClientIdentifierException, InvalidRedirectUriException};
use OAuth\Domain\Exception\Token\{AuthorizationException as OAuthAuthorizationException, InvalidGrantTypeException, InvalidScopeException, UnauthorizedGrantTypeException};
use OAuth\Domain\ValueObject\Client\{ClientId, RedirectUri};
use OAuth\Domain\ValueObject\Security\GrantType;
use OAuth\Presentation\Api\EventSubscriber\OAuthErrorSubscriber;
use OAuth\Presentation\Api\Operation\OAuthOperations;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Infrastructure\Exception\MessengerRuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, HttpException, TooManyRequestsHttpException, UnauthorizedHttpException};
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Throwable;

use function json_decode;

/**
 * Test OAuthErrorSubscriberTest.
 *
 * @category EventSubscriber Tests
 */
#[CoversClass(className: OAuthErrorSubscriber::class)]
final class OAuthErrorSubscriberTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testOnKernelExceptionIgnoresNonOAuthOperation(): void
  {
    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');

    $event = $this->createEvent(new RuntimeException('boom'), 'not_oauth');

    $subscriber->onKernelException($event);

    self::assertNull($event->getResponse());
  }

  #[Test]
  public function testOnKernelExceptionMapsTooManyRequests(): void
  {
    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');

    $exception = new TooManyRequestsHttpException(
      retryAfter: 10,
      message: 'Rate limit exceeded',
    );

    $event = $this->createEvent($exception, OAuthOperations::TOKEN);

    $subscriber->onKernelException($event);

    $response = $event->getResponse();
    self::assertNotNull($response);
    self::assertSame(expected: 429, actual: $response->getStatusCode());
    self::assertStringContainsString(needle: 'no-store', haystack: $response->headers->get('Cache-Control') ?? '');
    self::assertSame(expected: '10', actual: $response->headers->get('Retry-After'));

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray(actual: $body);
    self::assertSame(expected: 'temporarily_unavailable', actual: $body['error'] ?? null);
  }

  #[Test]
  public function testOnKernelExceptionMapsViolations(): void
  {
    $violationOne = $this->createMock(ConstraintViolationInterface::class);
    $violationOne->method('getPropertyPath')->willReturn('client_id');
    $violationOne->method('getMessage')->willReturn('This value should not be blank.');

    $violationTwo = $this->createMock(ConstraintViolationInterface::class);
    $violationTwo->method('getPropertyPath')->willReturn('');
    $violationTwo->method('getMessage')->willReturn('Payload invalid.');

    $exception = new class ([$violationOne, $violationTwo]) extends RuntimeException {
      /**
       * @param list<ConstraintViolationInterface> $violations
       */
      public function __construct(private array $violations)
      {
        parent::__construct('validation');
      }

      /**
       * @return list<ConstraintViolationInterface>
       */
      public function getViolations(): array
      {
        return $this->violations;
      }
    };

    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');
    $event = $this->createEvent($exception, OAuthOperations::TOKEN);

    $subscriber->onKernelException($event);

    $body = $this->decodeResponseBody($event);

    self::assertSame('invalid_request', $body['error'] ?? null);
    self::assertSame('client_id: This value should not be blank.; Payload invalid.', $body['error_description'] ?? null);
  }

  #[Test]
  public function testOnKernelExceptionMapsAuthorizationExceptionWithErrorUriBase(): void
  {
    $subscriber = new OAuthErrorSubscriber(errorUriBase: 'https://errors.example');
    $exception = OAuthAuthorizationException::invalidClient('Invalid client provided');

    $event = $this->createEvent($exception, OAuthOperations::TOKEN);

    $subscriber->onKernelException($event);

    $response = $event->getResponse();
    self::assertNotNull($response);
    self::assertSame(401, $response->getStatusCode());
    self::assertSame('Basic realm="OAuth", error="invalid_client"', $response->headers->get('WWW-Authenticate'));

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    /** @var array<string, mixed> $body */
    self::assertSame('invalid_client', $body['error'] ?? null);
    self::assertSame('Client authentication failed.', $body['error_description'] ?? null);
    self::assertSame('https://errors.example#invalid_client', $body['error_uri'] ?? null);
  }

  #[Test]
  public function testOnKernelExceptionMapsOAuthServerException(): void
  {
    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');
    $exception = new OAuthServerException('Scope invalid', 0, 'invalid_scope', 400);

    $event = $this->createEvent($exception, OAuthOperations::TOKEN);

    $subscriber->onKernelException($event);

    $body = $this->decodeResponseBody($event);

    self::assertSame('invalid_scope', $body['error'] ?? null);
    self::assertSame('Scope invalid', $body['error_description'] ?? null);
  }

  #[Test]
  public function testOnKernelExceptionMapsHttpExceptionWithHeaders(): void
  {
    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');

    $exception = new BadRequestHttpException('Bad request', null, 0, [
      'X-Test' => ['a', 'b'],
      'X-Scalar' => 1,
    ]);

    $event = $this->createEvent($exception, OAuthOperations::TOKEN);

    $subscriber->onKernelException($event);

    $response = $event->getResponse();
    self::assertNotNull($response);
    self::assertSame(400, $response->getStatusCode());
    self::assertSame('a,b', $response->headers->get('X-Test'));
    self::assertSame('1', $response->headers->get('X-Scalar'));

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);
    self::assertSame('invalid_request', $body['error'] ?? null);
    self::assertSame('Bad request', $body['error_description'] ?? null);
  }

  #[Test]
  public function testOnKernelExceptionUnwrapsMessengerException(): void
  {
    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');

    $inner = OAuthAuthorizationException::invalidGrant('Invalid grant');
    $handlerFailed = new HandlerFailedException(new Envelope(new stdClass()), ['handler' => $inner]);
    $exception = MessengerRuntimeException::wrap($handlerFailed);

    $event = $this->createEvent($exception, OAuthOperations::TOKEN);

    $subscriber->onKernelException($event);

    $body = $this->decodeResponseBody($event);

    self::assertSame('invalid_grant', $body['error'] ?? null);
    self::assertSame('Invalid grant', $body['error_description'] ?? null);
  }

  #[Test]
  #[DataProvider('domainExceptionProvider')]
  public function testOnKernelExceptionMapsDomainExceptions(Throwable $exception, string $expectedError): void
  {
    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');
    $event = $this->createEvent($exception, OAuthOperations::TOKEN);

    $subscriber->onKernelException($event);

    $body = $this->decodeResponseBody($event);

    self::assertSame($expectedError, $body['error'] ?? null);
  }

  #[Test]
  public function testOnKernelExceptionMapsUnknownExceptionToServerError(): void
  {
    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');
    $event = $this->createEvent(new RuntimeException('unexpected'), OAuthOperations::TOKEN);

    $subscriber->onKernelException($event);

    $body = $this->decodeResponseBody($event);

    self::assertSame('server_error', $body['error'] ?? null);
  }

  #[Test]
  public function testGetSubscribedEventsRegistersKernelException(): void
  {
    $events = OAuthErrorSubscriber::getSubscribedEvents();

    self::assertArrayHasKey(\Symfony\Component\HttpKernel\KernelEvents::EXCEPTION, $events);
  }

  #[Test]
  public function testOnKernelExceptionUnwrapsMessengerExceptionPreviousChain(): void
  {
    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');

    $inner = OAuthServerException::invalidRequest('client_id');
    $outer = new RuntimeException('wrapper', 0, $inner);
    $exception = MessengerRuntimeException::wrap($outer);

    $event = $this->createEvent($exception, OAuthOperations::TOKEN);

    $subscriber->onKernelException($event);

    $body = $this->decodeResponseBody($event);

    self::assertSame('invalid_request', $body['error'] ?? null);
  }

  #[Test]
  public function testOnKernelExceptionUnwrapsHandlerFailedExceptionDirect(): void
  {
    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');

    $inner = OAuthServerException::invalidRequest('client_id');
    $handlerFailed = new HandlerFailedException(new Envelope(new stdClass()), [$inner]);

    $event = $this->createEvent($handlerFailed, OAuthOperations::TOKEN);

    $subscriber->onKernelException($event);

    $body = $this->decodeResponseBody($event);

    self::assertSame('invalid_request', $body['error'] ?? null);
  }

  #[Test]
  #[DataProvider('httpStatusProvider')]
  public function testOnKernelExceptionMapsHttpStatusErrors(Throwable $exception, string $expectedError): void
  {
    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');
    $event = $this->createEvent($exception, OAuthOperations::TOKEN);

    $subscriber->onKernelException($event);

    $body = $this->decodeResponseBody($event);

    self::assertSame($expectedError, $body['error'] ?? null);
  }

  #[Test]
  #[DataProvider('emptyDescriptionProvider')]
  public function testOnKernelExceptionUsesDefaultDescriptions(Throwable $exception, string $expectedError, string $expectedDescription): void
  {
    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');
    $event = $this->createEvent($exception, OAuthOperations::TOKEN);

    $subscriber->onKernelException($event);

    $body = $this->decodeResponseBody($event);

    self::assertSame($expectedError, $body['error'] ?? null);
    self::assertSame($expectedDescription, $body['error_description'] ?? null);
  }

  #[Test]
  public function testOnKernelExceptionIgnoresNonIterableViolations(): void
  {
    $exception = new class () extends RuntimeException {
      public function getViolations(): string
      {
        return 'not-iterable';
      }
    };

    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');
    $event = $this->createEvent($exception, OAuthOperations::TOKEN);

    $subscriber->onKernelException($event);

    $body = $this->decodeResponseBody($event);

    self::assertSame('server_error', $body['error'] ?? null);
  }

  #[Test]
  public function testOnKernelExceptionIgnoresNonConstraintViolations(): void
  {
    $exception = new class () extends RuntimeException {
      /**
       * @return list<object>
       */
      public function getViolations(): array
      {
        return [new stdClass()];
      }
    };

    $subscriber = new OAuthErrorSubscriber(errorUriBase: '');
    $event = $this->createEvent($exception, OAuthOperations::TOKEN);

    $subscriber->onKernelException($event);

    $body = $this->decodeResponseBody($event);

    self::assertSame('server_error', $body['error'] ?? null);
  }
  // #endregion

  // #region Providers
  /**
   * @return array<string, array{Throwable, string}>
   */
  public static function domainExceptionProvider(): array
  {
    return [
      'invalid_grant_type' => [InvalidGrantTypeException::empty(), 'unsupported_grant_type'],
      'unauthorized_grant_type' => [UnauthorizedGrantTypeException::forGrantType(GrantType::AUTHORIZATION_CODE), 'unauthorized_client'],
      'invalid_scope' => [InvalidScopeException::invalidFormat('bad'), 'invalid_scope'],
      'invalid_client' => [InvalidClientException::forId(new ClientId('123e4567-e89b-12d3-a456-426614174000')), 'invalid_client'],
      'invalid_client_identifier' => [InvalidOAuthClientIdentifierException::invalidPattern('bad'), 'invalid_request'],
      'invalid_redirect_uri' => [InvalidRedirectUriException::forUri(new RedirectUri('https://example.com/callback')), 'invalid_request'],
    ];
  }

  /**
   * @return array<string, array{0: Throwable, 1: string}>
   */
  public static function httpStatusProvider(): array
  {
    return [
      'unauthorized' => [new UnauthorizedHttpException('Bearer', ''), 'invalid_client'],
      'forbidden' => [new AccessDeniedHttpException(''), 'access_denied'],
      'server_error' => [new HttpException(500, ''), 'server_error'],
    ];
  }

  /**
   * @return array<string, array{0: Throwable, 1: string, 2: string}>
   */
  public static function emptyDescriptionProvider(): array
  {
    return [
      'invalid_request' => [OAuthAuthorizationException::invalidRequest(''), 'invalid_request', 'Invalid request.'],
      'invalid_grant' => [OAuthAuthorizationException::invalidGrant(''), 'invalid_grant', 'Invalid grant.'],
      'invalid_scope' => [new OAuthServerException('', 0, 'invalid_scope', 400), 'invalid_scope', 'Invalid scope.'],
      'unauthorized_client' => [OAuthAuthorizationException::unauthorizedClient(''), 'unauthorized_client', 'Unauthorized client.'],
      'unsupported_grant_type' => [OAuthAuthorizationException::unsupportedGrantType(''), 'unsupported_grant_type', 'Unsupported grant type.'],
      'access_denied' => [OAuthAuthorizationException::accessDenied(''), 'access_denied', 'Access denied.'],
      'default' => [new OAuthServerException('', 0, 'invalid_token', 400), 'invalid_token', 'Authorization error.'],
    ];
  }
  // #endregion

  // #region Helpers
  private function createEvent(Throwable $exception, ?string $operationName): ExceptionEvent
  {
    $request = new Request();
    if (null !== $operationName) {
      $request->attributes->set('_api_operation_name', $operationName);
    }

    $kernel = $this->createMock(HttpKernelInterface::class);

    return new ExceptionEvent(
      kernel: $kernel,
      request: $request,
      requestType: HttpKernelInterface::MAIN_REQUEST,
      e: $exception,
    );
  }

  /**
   * @return array<string, mixed>
   */
  private function decodeResponseBody(ExceptionEvent $event): array
  {
    $response = $event->getResponse();
    self::assertNotNull($response);

    $body = json_decode((string) $response->getContent(), true);
    self::assertIsArray($body);

    /** @var array<string, mixed> $body */
    return $body;
  }
  // #endregion
}
