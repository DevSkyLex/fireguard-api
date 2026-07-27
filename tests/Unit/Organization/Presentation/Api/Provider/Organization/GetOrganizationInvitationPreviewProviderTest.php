<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use DateTimeImmutable;
use Organization\Application\UseCase\Query\Organization\GetOrganizationInvitationPreview\{
  GetOrganizationInvitationPreviewQuery,
  GetOrganizationInvitationPreviewResult
};
use Organization\Domain\Exception\OrganizationInvitationNotFoundException;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationInvitationPreviewProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{NotFoundHttpException, TooManyRequestsHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Test GetOrganizationInvitationPreviewProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetOrganizationInvitationPreviewProvider::class)]
final class GetOrganizationInvitationPreviewProviderTest extends TestCase
{
  // #region Constants
  private const string TOKEN = 'invitation-token';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string IP = '203.0.113.10';
  // #endregion

  // #region Methods
  #[Test]
  public function testProvideReturnsThePreviewOutput(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetOrganizationInvitationPreviewQuery $query): bool => self::TOKEN === $query->token))
      ->willReturn($this->invitationResult());

    $output = $this->createProvider($queryBus)->provide(new Get(), ['token' => self::TOKEN]);

    self::assertSame(self::ORGANIZATION_ID, $output->organizationId);
    self::assertSame('Fireguard Test', $output->organizationName);
    self::assertSame('invitee@example.com', $output->invitedEmail);
    self::assertSame('pending', $output->status);
    self::assertSame('2026-02-01T00:00:00+00:00', $output->expiresAt);
  }

  #[Test]
  public function testProvideThrowsWhenTheTokenIsMissing(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Invitation token is required.');

    $this->createProvider()->provide(new Get(), []);
  }

  #[Test]
  public function testProvideMapsAMissingInvitationToHttp404(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(OrganizationInvitationNotFoundException::withToken());

    $this->expectException(NotFoundHttpException::class);

    $this->createProvider($queryBus)->provide(new Get(), ['token' => self::TOKEN]);
  }

  #[Test]
  public function testProvideUnwrapsAMissingInvitationWrappedByTheMessengerBus(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessengerRuntimeException::wrap(
      new HandlerFailedException(
        new Envelope(new GetOrganizationInvitationPreviewQuery(self::TOKEN)),
        [OrganizationInvitationNotFoundException::withToken()],
      ),
    ));

    $this->expectException(NotFoundHttpException::class);

    $this->createProvider($queryBus)->provide(new Get(), ['token' => self::TOKEN]);
  }

  #[Test]
  public function testProvideRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(
      MessengerRuntimeException::wrap(new RuntimeException('database is down')),
    );

    $this->expectException(MessengerRuntimeException::class);

    $this->createProvider($queryBus)->provide(new Get(), ['token' => self::TOKEN]);
  }

  #[Test]
  public function testProvideThrowsTooManyRequestsWhenTheRateLimitIsExhausted(): void
  {
    $rateLimiter = $this->createRateLimiterFactory(limit: 1);
    $rateLimiter->create(self::IP)->consume();

    $provider = $this->createProvider(rateLimiter: $rateLimiter);

    $this->expectException(TooManyRequestsHttpException::class);

    $provider->provide(new Get(), ['token' => self::TOKEN]);
  }

  #[Test]
  public function testProvideSkipsRateLimitingWhenNoLimiterIsWired(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($this->invitationResult());

    $provider = new GetOrganizationInvitationPreviewProvider(
      queryBus: $queryBus,
      requestStack: $this->requestStack(),
      rateLimiter: null,
    );

    self::assertSame(
      self::ORGANIZATION_ID,
      $provider->provide(new Get(), ['token' => self::TOKEN])->organizationId,
    );
  }

  private function createProvider(
    ?QueryBusPort $queryBus = null,
    ?RateLimiterFactory $rateLimiter = null,
  ): GetOrganizationInvitationPreviewProvider {
    if (null === $queryBus) {
      $queryBus = $this->createStub(QueryBusPort::class);
      $queryBus->method('ask')->willReturn($this->invitationResult());
    }

    return new GetOrganizationInvitationPreviewProvider(
      queryBus: $queryBus,
      requestStack: $this->requestStack(),
      rateLimiter: $rateLimiter ?? $this->createRateLimiterFactory(),
    );
  }

  private function requestStack(): RequestStack
  {
    $stack = new RequestStack();
    $stack->push(new Request(server: ['REMOTE_ADDR' => self::IP]));

    return $stack;
  }

  private function createRateLimiterFactory(int $limit = 10): RateLimiterFactory
  {
    return new RateLimiterFactory(
      config: [
        'id' => 'invitation_preview',
        'policy' => 'fixed_window',
        'limit' => $limit,
        'interval' => '1 hour',
      ],
      storage: new InMemoryStorage(),
    );
  }

  private function invitationResult(): GetOrganizationInvitationPreviewResult
  {
    return new GetOrganizationInvitationPreviewResult(
      organizationId: self::ORGANIZATION_ID,
      organizationName: 'Fireguard Test',
      organizationLogoUrl: null,
      inviterDisplayName: 'Owner User',
      invitedEmail: 'invitee@example.com',
      status: 'pending',
      expiresAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
    );
  }
  // #endregion
}
