<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use User\Application\UseCase\Command\EmailChange\ConfirmEmailChange\ConfirmEmailChangeResult;
use User\Application\UseCase\Command\EmailChange\RequestEmailChange\RequestEmailChangeResult;

use function json_decode;
use function json_encode;

/**
 * Test EmailChangeApiTest.
 *
 * HTTP contract of the email change endpoints. The request and cancel
 * operations are authenticated (401 without a token); the confirm
 * operation is public — the emailed token is the credential — and maps
 * an unknown/expired/reused token to one neutral 400.
 *
 * @category Functional Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailChangeApiTest extends WebTestCase
{
  private const string USER_ID = '00000000-0000-4000-a000-00000000e401';

  // #region Properties
  private ?KernelBrowser $client = null;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    $this->client = static::createClient();
  }

  protected function tearDown(): void
  {
    $this->client = null;
    self::ensureKernelShutdown();
  }
  // #endregion

  // #region Tests
  #[Test]
  public function testRequestEmailChangeRequiresAuthentication(): void
  {
    $this->client?->request(
      method: 'POST',
      uri: '/api/me/email-change',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'newEmail' => 'new-address@example.com',
        'currentPassword' => 'CurrentP@ssw0rd!',
      ]) ?: '',
    );

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    self::assertNotSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
  }

  #[Test]
  public function testCancelEmailChangeRequiresAuthentication(): void
  {
    $this->client?->request(
      method: 'DELETE',
      uri: '/api/me/email-change',
      server: ['HTTP_ACCEPT' => 'application/ld+json'],
    );

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    self::assertNotSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
  }

  #[Test]
  public function testConfirmEmailChangeIsPublicAndRejectsMissingToken(): void
  {
    // No Authorization header at all: the route must be reachable (not
    // 401) and refuse the empty payload with a validation 4xx.
    $this->client?->request(
      method: 'POST',
      uri: '/api/me/email-change/confirm',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([]) ?: '',
    );

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    self::assertNotSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    self::assertNotSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    self::assertContains($response->getStatusCode(), [
      Response::HTTP_BAD_REQUEST,
      Response::HTTP_UNPROCESSABLE_ENTITY,
    ]);
  }

  #[Test]
  public function testConfirmEmailChangeMapsInvalidTokenToNeutral400(): void
  {
    $this->setCommandBus(ConfirmEmailChangeResult::failed(
      message: 'Invalid or expired email change token.',
      errorCode: ConfirmEmailChangeResult::ERROR_INVALID_TOKEN,
    ));

    $this->client?->request(
      method: 'POST',
      uri: '/api/me/email-change/confirm',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode(['token' => 'definitely-not-a-valid-token']) ?: '',
    );

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    self::assertStringContainsString(
      'Invalid or expired email change token.',
      (string) $response->getContent(),
    );
  }

  #[Test]
  public function testConfirmEmailChangeReturns200OnSuccess(): void
  {
    $this->setCommandBus(ConfirmEmailChangeResult::success());

    $this->client?->request(
      method: 'POST',
      uri: '/api/me/email-change/confirm',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode(['token' => 'a-syntactically-fine-token']) ?: '',
    );

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    self::assertSame(Response::HTTP_OK, $response->getStatusCode());

    /** @var array<string, mixed> $payload */
    $payload = json_decode((string) $response->getContent(), true);
    self::assertTrue($payload['success']);
  }

  #[Test]
  public function testRequestEmailChangeBeyondThePerUserBudgetIs429(): void
  {
    // Neither email_change limiter is overridden in
    // config/packages/test/rate_limiter.yaml, so the real 5/min budget is
    // live here. Pre-loading through the container (no HTTP round trip)
    // sidesteps the kernel.reset wipe of the array cache pool between
    // requests — same technique, same rationale, as
    // CalendarFeedIcsRateLimitApiTest.
    $this->setCommandBus(RequestEmailChangeResult::success(expiresAt: new DateTimeImmutable('+1 hour')));

    /** @var RateLimiterFactory $limiterFactory */
    $limiterFactory = static::getContainer()->get('limiter.email_change_request');
    for ($i = 0; $i < 5; ++$i) {
      self::assertTrue(
        $limiterFactory->create(self::USER_ID)->consume()->isAccepted(),
        "Pre-loading consume #{$i} must still be accepted (budget is 5).",
      );
    }

    $this->requestEmailChangeAs(self::USER_ID);

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    self::assertSame(
      Response::HTTP_TOO_MANY_REQUESTS,
      $response->getStatusCode(),
      'The 6th request within a minute must answer 429. Response: ' . ((string) $response->getContent()),
    );
  }

  #[Test]
  public function testRequestEmailChangeBeyondThePerIpBudgetIs429ForAFreshUser(): void
  {
    // Per-IP dimension: exhaust email_change_request_ip for the test
    // client's IP (127.0.0.1) while the per-user budget of the caller is
    // untouched — the request must still be refused, which is what stops
    // an attacker from scaling the per-user budget with many accounts.
    $this->setCommandBus(RequestEmailChangeResult::success(expiresAt: new DateTimeImmutable('+1 hour')));

    /** @var RateLimiterFactory $ipLimiterFactory */
    $ipLimiterFactory = static::getContainer()->get('limiter.email_change_request_ip');
    for ($i = 0; $i < 5; ++$i) {
      self::assertTrue(
        $ipLimiterFactory->create('127.0.0.1')->consume()->isAccepted(),
        "Pre-loading consume #{$i} must still be accepted (budget is 5).",
      );
    }

    $this->requestEmailChangeAs('00000000-0000-4000-a000-00000000e429');

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    self::assertSame(
      Response::HTTP_TOO_MANY_REQUESTS,
      $response->getStatusCode(),
      'A fresh user behind an exhausted IP budget must still get 429. Response: ' . ((string) $response->getContent()),
    );
  }

  #[Test]
  public function testRequestEmailChangeWithinTheBudgetsIsNotThrottled(): void
  {
    // Control: with both buckets fresh, the first request goes through.
    $this->setCommandBus(RequestEmailChangeResult::success(expiresAt: new DateTimeImmutable('+1 hour')));

    $this->requestEmailChangeAs('00000000-0000-4000-a000-00000000e430');

    $response = $this->client?->getResponse();
    self::assertNotNull($response);
    self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode(), (string) $response->getContent());
  }
  // #endregion

  // #region Helpers
  /**
   * Authenticates against the stateless `api` firewall (token in the
   * container, no session, no database user needed — the command bus is
   * stubbed) and posts one email change request.
   */
  private function requestEmailChangeAs(string $userId): void
  {
    $this->client?->loginUser(new SecurityUser(
      id: $userId,
      email: 'rate-limit-' . $userId . '@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
    ), 'api');

    $this->client?->request(
      method: 'POST',
      uri: '/api/me/email-change',
      server: [
        'CONTENT_TYPE' => 'application/ld+json',
        'HTTP_ACCEPT' => 'application/ld+json',
      ],
      content: json_encode([
        'newEmail' => 'new-address@example.com',
        'currentPassword' => 'CurrentP@ssw0rd!',
      ]) ?: '',
    );
  }

  private function setCommandBus(ResultMessage $result): void
  {
    static::getContainer()->set(
      id: CommandBusPort::class,
      service: new EmailChangeTestCommandBus($result),
    );
  }
  // #endregion
}

/**
 * Command bus double returning one fixed result.
 *
 * @category Functional Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailChangeTestCommandBus implements CommandBusPort
{
  public function __construct(private ResultMessage $result)
  {
  }

  public function dispatch(CommandMessage $command): ResultMessage
  {
    return $this->result;
  }
}
