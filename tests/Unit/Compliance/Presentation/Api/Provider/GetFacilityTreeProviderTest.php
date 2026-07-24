<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Compliance\Application\UseCase\Query\GetFacilityTree\{GetFacilityTreeQuery, GetFacilityTreeResult};
use Compliance\Domain\Exception\ComplianceAccessDeniedException;
use Compliance\Presentation\Api\Dto\Output\FacilityTreeOutput;
use Compliance\Presentation\Api\Factory\FacilityTreeOutputFactory;
use Compliance\Presentation\Api\Provider\GetFacilityTreeProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Test GetFacilityTreeProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityTreeProvider::class)]
final class GetFacilityTreeProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655441610';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441600';

  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    $provider = new GetFacilityTreeProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      outputFactory: new FacilityTreeOutputFactory(),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $provider->provide(new Get(), ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProvideThrowsWhenOrganizationIdIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser());

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetFacilityTreeProvider(
      queryBus: $queryBus,
      outputFactory: new FacilityTreeOutputFactory(),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), []);
  }

  #[Test]
  public function testProvideAsksTheQueryBusAndMapsTheResultThroughTheFactory(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser());

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetFacilityTreeQuery $query): bool => self::ORG_ID === $query->organizationId && self::USER_ID === $query->userId))
      ->willReturn(new GetFacilityTreeResult(generatedAt: '2026-01-01T00:00:00+00:00', tree: []));

    $provider = new GetFacilityTreeProvider(
      queryBus: $queryBus,
      outputFactory: new FacilityTreeOutputFactory(),
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => self::ORG_ID]);

    self::assertInstanceOf(FacilityTreeOutput::class, $output);
    self::assertSame(self::ORG_ID, $output->organizationId);
    self::assertSame('2026-01-01T00:00:00+00:00', $output->generatedAt);
    self::assertSame([], $output->nodes);
  }

  #[Test]
  public function testProvideMapsComplianceAccessDeniedExceptionToHttp403(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser());

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(new ComplianceAccessDeniedException('Missing organization.compliance.read permission.'));

    $provider = new GetFacilityTreeProvider(
      queryBus: $queryBus,
      outputFactory: new FacilityTreeOutputFactory(),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => self::ORG_ID]);
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
