<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Query\Organization\GetNavigationCounters\{GetNavigationCountersQuery, GetNavigationCountersResult};
use Organization\Domain\Exception\OrganizationMemberNotFoundException;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationNavigationCountersOutput;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationNavigationCountersProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Test GetOrganizationNavigationCountersProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetOrganizationNavigationCountersProvider::class)]
final class GetOrganizationNavigationCountersProviderTest extends TestCase
{
  #[Test]
  public function testProvideReturnsNullWhenOrganizationIdMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655443401'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationNavigationCountersProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $output = $provider->provide(new Get(), []);

    self::assertNull($output);
  }

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $provider = new GetOrganizationNavigationCountersProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655443402']);
  }

  #[Test]
  public function testProvideMapsNavigationCounters(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655443403'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetNavigationCountersQuery $query): bool => '550e8400-e29b-41d4-a716-446655443404' === $query->organizationId
        && '550e8400-e29b-41d4-a716-446655443403' === $query->userId))
      ->willReturn(new GetNavigationCountersResult(openInterventions: 4, openNonConformities: 5));

    $provider = new GetOrganizationNavigationCountersProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655443404']);

    self::assertInstanceOf(OrganizationNavigationCountersOutput::class, $output);
    self::assertSame(4, $output->openInterventions);
    self::assertSame(5, $output->openNonConformities);
  }

  #[Test]
  public function testProvideMapsMissingMembershipToNotFound(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655443405'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(OrganizationMemberNotFoundException::forUserInOrganization(
        '550e8400-e29b-41d4-a716-446655443405',
        '550e8400-e29b-41d4-a716-446655443406',
      ));

    $provider = new GetOrganizationNavigationCountersProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655443406']);
  }

  #[Test]
  public function testProvideUnwrapsAMissingMembershipWrappedByTheMessengerBus(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655443405'));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(MessengerRuntimeException::wrap(
      new HandlerFailedException(
        new Envelope(new GetNavigationCountersQuery(
          '550e8400-e29b-41d4-a716-446655443406',
          '550e8400-e29b-41d4-a716-446655443405',
        )),
        [OrganizationMemberNotFoundException::forUserInOrganization(
          '550e8400-e29b-41d4-a716-446655443405',
          '550e8400-e29b-41d4-a716-446655443406',
        )],
      ),
    ));

    $provider = new GetOrganizationNavigationCountersProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655443406']);
  }

  #[Test]
  public function testProvideRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655443405'));

    $failure = MessengerRuntimeException::wrap(
      new HandlerFailedException(
        new Envelope(new GetNavigationCountersQuery(
          '550e8400-e29b-41d4-a716-446655443406',
          '550e8400-e29b-41d4-a716-446655443405',
        )),
        [new RuntimeException('the read model is offline')],
      ),
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($failure);

    $provider = new GetOrganizationNavigationCountersProvider(
      queryBus: $queryBus,
      security: $security,
    );

    $this->expectExceptionObject($failure);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655443406']);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
