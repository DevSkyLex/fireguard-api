<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Intervention\Application\Contract\Publication\PublicationView;
use Intervention\Application\UseCase\Query\Publication\GetPublication\{
  GetPublicationQuery,
  GetPublicationResult
};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, PublicationNotFoundException};
use Intervention\Presentation\Api\Factory\PublicationOutputFactory;
use Intervention\Presentation\Api\Provider\PublicationProvider;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Test PublicationProviderTest.
 *
 * A publication record exposes the failure reason of a rejected publish, so
 * it must never be readable by someone outside the owning organization. The
 * provider also checks the route parameter before authentication, and that
 * ordering is deliberate — it is pinned here so it cannot drift.
 *
 * @category Provider Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PublicationProvider::class)]
final class PublicationProviderTest extends TestCase
{
  // #region Constants
  private const string PUBLICATION_ID = '550e8400-e29b-41d4-a716-446655486001';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655486002';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655486003';
  // #endregion

  // #region Methods
  /**
   * @return iterable<string, array{Throwable, class-string<Throwable>}>
   */
  public static function domainFailureProvider(): iterable
  {
    yield 'not entitled' => [
      new InterventionAccessDeniedException('Not your publication.'),
      AccessDeniedHttpException::class,
    ];
    yield 'unknown publication' => [
      PublicationNotFoundException::withId(self::PUBLICATION_ID),
      NotFoundHttpException::class,
    ];
  }

  #[Test]
  public function testProvideReturnsTheMappedPublication(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetPublicationQuery $query): bool => self::USER_ID === $query->userId
        && self::PUBLICATION_ID === $query->publicationId))
      ->willReturn(new GetPublicationResult($this->view()));

    $output = $this->createProvider($queryBus)->provide(new Get(), ['id' => self::PUBLICATION_ID]);

    self::assertSame(self::PUBLICATION_ID, $output->id);
    self::assertSame('/api/interventions/' . self::INTERVENTION_ID, $output->intervention);
    self::assertSame('failed', $output->status);
    self::assertSame('downstream rejected the payload', $output->error);
    self::assertSame('2026-01-01T00:10:00+00:00', $output->completedAt);
  }

  #[Test]
  public function testProvideReturnsNotFoundWhenTheIdIsMissing(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Publication not found.');

    $this->createProvider($queryBus)->provide(new Get(), []);
  }

  #[Test]
  public function testProvideThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new PublicationProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      outputMapper: new PublicationOutputFactory(),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $provider->provide(new Get(), ['id' => self::PUBLICATION_ID]);
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('domainFailureProvider')]
  public function testProvideMapsEachDirectFailureToItsHttpStatus(Throwable $failure, string $expected): void
  {
    $this->expectException($expected);

    $this->providerThrowing($failure)->provide(new Get(), ['id' => self::PUBLICATION_ID]);
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('domainFailureProvider')]
  public function testProvideUnwrapsEachMessengerWrappedFailure(Throwable $failure, string $expected): void
  {
    $this->expectException($expected);

    $this->providerThrowing($this->wrapped($failure))->provide(new Get(), ['id' => self::PUBLICATION_ID]);
  }

  #[Test]
  public function testProvideRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $this->expectException(MessengerRuntimeException::class);

    $this->providerThrowing($this->wrapped(new RuntimeException('database is down')))
      ->provide(new Get(), ['id' => self::PUBLICATION_ID]);
  }

  private function wrapped(Throwable $failure): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new GetPublicationQuery(self::USER_ID, self::PUBLICATION_ID)),
      [$failure],
    ));
  }

  private function providerThrowing(Throwable $failure): PublicationProvider
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($failure);

    return $this->createProvider($queryBus);
  }

  private function createProvider(QueryBusPort $queryBus): PublicationProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return new PublicationProvider(
      queryBus: $queryBus,
      outputMapper: new PublicationOutputFactory(),
      security: $security,
    );
  }

  private function view(): PublicationView
  {
    return new PublicationView(
      id: self::PUBLICATION_ID,
      interventionId: self::INTERVENTION_ID,
      interventionRevision: 3,
      status: 'failed',
      error: 'downstream rejected the payload',
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      completedAt: new DateTimeImmutable('2026-01-01T00:10:00+00:00'),
    );
  }
  // #endregion
}
