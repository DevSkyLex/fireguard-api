<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Application\UseCase\Query\GetComplianceOverview\{GetComplianceOverviewQuery, GetComplianceOverviewResult};
use Compliance\Domain\Exception\{ComplianceAccessDeniedException, ComplianceNotFoundException};
use Compliance\Domain\ValueObject\ComplianceStatus;
use Compliance\Presentation\Api\Factory\ComplianceSummaryOutputFactory;
use Compliance\Presentation\Api\Provider\GetComplianceOverviewProvider;
use InvalidArgumentException;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

/**
 * Test GetComplianceOverviewProviderTest.
 *
 * The organization register is the regulator-facing rollup, so the
 * organization it is built for must come from the URI and an unauthenticated
 * or unscoped call has to be refused before the query bus is ever reached.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetComplianceOverviewProvider::class)]
final class GetComplianceOverviewProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655504001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655504002';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655504003';

  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function missingOrganizationProvider(): iterable
  {
    yield 'no variables' => [[]];
    yield 'blank organizationId' => [['organizationId' => '']];
    yield 'non-string organizationId' => [['organizationId' => 42]];
  }

  /**
   * @return iterable<string, array{Throwable, class-string<Throwable>}>
   */
  public static function domainFailureProvider(): iterable
  {
    yield 'compliance access denied' => [
      new ComplianceAccessDeniedException('Not your organization.'),
      AccessDeniedHttpException::class,
    ];
    yield 'organization access denied' => [
      OrganizationAccessDeniedException::missingPermission('organization.compliance.read'),
      AccessDeniedHttpException::class,
    ];
    yield 'not found' => [
      ComplianceNotFoundException::facilityNotFound(self::FACILITY_ID),
      NotFoundHttpException::class,
    ];
    yield 'invalid argument' => [
      new InvalidArgumentException('Malformed organization identifier.'),
      BadRequestHttpException::class,
    ];
  }

  #[Test]
  public function testItReturnsTheOrganizationRegister(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetComplianceOverviewQuery $query): bool => self::ORGANIZATION_ID === $query->organizationId
        && self::USER_ID === $query->userId))
      ->willReturn($this->overviewResult());

    $output = $this->createProvider($queryBus)->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertSame(self::ORGANIZATION_ID, $output->organizationId);
    self::assertNull($output->facilityId);
    self::assertSame('2026-06-01T08:00:00+00:00', $output->generatedAt);
    self::assertSame('at_risk', $output->organizationStatus);
    self::assertSame(10, $output->totals['totalEquipmentCount']);
    self::assertCount(1, $output->facilities);
    self::assertSame(self::FACILITY_ID, $output->facilities[0]['facilityId']);
    self::assertSame(8, $output->facilities[0]['trackedEquipmentCount']);
    self::assertSame(75.0, $output->facilities[0]['complianceRate']);
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetComplianceOverviewProvider(
      queryBus: $queryBus,
      outputFactory: new ComplianceSummaryOutputFactory(),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $provider->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('missingOrganizationProvider')]
  public function testItRefusesAnUnscopedCall(array $uriVariables): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $this->expectException(AccessDeniedHttpException::class);

    $this->createProvider($queryBus)->provide(new Get(), $uriVariables);
  }

  /**
   * @param class-string<Throwable> $expected
   */
  #[Test]
  #[DataProvider('domainFailureProvider')]
  public function testItMapsEachDomainFailureToItsHttpStatus(Throwable $failure, string $expected): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($failure);

    $this->expectException($expected);

    $this->createProvider($queryBus)->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testItRethrowsAnUnrecognisedFailure(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('database is down'));

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('database is down');

    $this->createProvider($queryBus)->provide(new Get(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  private function createProvider(QueryBusPort $queryBus): GetComplianceOverviewProvider
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

    return new GetComplianceOverviewProvider(
      queryBus: $queryBus,
      outputFactory: new ComplianceSummaryOutputFactory(),
      security: $security,
    );
  }

  private function overviewResult(): GetComplianceOverviewResult
  {
    return new GetComplianceOverviewResult(
      generatedAt: '2026-06-01T08:00:00+00:00',
      organizationStatus: ComplianceStatus::AT_RISK,
      totals: [
        'totalEquipmentCount' => 10,
        'activeEquipmentCount' => 9,
        'upToDateEquipmentCount' => 6,
        'dueSoonEquipmentCount' => 2,
        'overdueEquipmentCount' => 0,
        'unscheduledEquipmentCount' => 1,
        'trackedEquipmentCount' => 8,
        'complianceRate' => 75.0,
        'openLowNonConformityCount' => 0,
        'openMediumNonConformityCount' => 1,
        'openHighNonConformityCount' => 1,
        'openCriticalNonConformityCount' => 0,
      ],
      facilities: [
        new FacilityComplianceView(
          facilityId: self::FACILITY_ID,
          name: 'Site principal',
          type: 'site',
          parentFacilityId: null,
          path: 'Site principal',
          status: ComplianceStatus::AT_RISK,
          totalEquipmentCount: 10,
          activeEquipmentCount: 9,
          upToDateEquipmentCount: 6,
          dueSoonEquipmentCount: 2,
          overdueEquipmentCount: 0,
          unscheduledEquipmentCount: 1,
          openLowNonConformityCount: 0,
          openMediumNonConformityCount: 1,
          openHighNonConformityCount: 1,
          openCriticalNonConformityCount: 0,
          lastInspectionAt: '2026-05-20T10:00:00+00:00',
        ),
      ],
    );
  }
}
