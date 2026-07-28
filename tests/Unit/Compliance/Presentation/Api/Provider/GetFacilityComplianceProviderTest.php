<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Application\UseCase\Query\GetFacilityCompliance\{GetFacilityComplianceQuery, GetFacilityComplianceResult};
use Compliance\Domain\Exception\{ComplianceAccessDeniedException, ComplianceNotFoundException};
use Compliance\Domain\ValueObject\ComplianceStatus;
use Compliance\Presentation\Api\Factory\ComplianceSummaryOutputFactory;
use Compliance\Presentation\Api\Provider\GetFacilityComplianceProvider;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

/**
 * Test GetFacilityComplianceProviderTest.
 *
 * A missing or blank facility identifier must read as 404, never as a
 * silently unscoped register: the single-facility view is what a site
 * manager signs off on.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityComplianceProvider::class)]
final class GetFacilityComplianceProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655505001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655505002';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655505003';

  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function incompleteUriVariablesProvider(): iterable
  {
    yield 'no variables' => [[]];
    yield 'blank organizationId' => [['organizationId' => '', 'facilityId' => self::FACILITY_ID]];
    yield 'missing facilityId' => [['organizationId' => self::ORGANIZATION_ID]];
    yield 'blank facilityId' => [['organizationId' => self::ORGANIZATION_ID, 'facilityId' => '']];
    yield 'non-string facilityId' => [['organizationId' => self::ORGANIZATION_ID, 'facilityId' => 7]];
  }

  /**
   * @return iterable<string, array{Throwable, class-string<Throwable>}>
   */
  public static function domainFailureProvider(): iterable
  {
    yield 'compliance access denied' => [
      new ComplianceAccessDeniedException('Facility belongs to another organization.'),
      AccessDeniedHttpException::class,
    ];
    yield 'facility not found' => [
      ComplianceNotFoundException::facilityNotFound(self::FACILITY_ID),
      NotFoundHttpException::class,
    ];
    yield 'invalid argument' => [
      new InvalidArgumentException('Malformed facility identifier.'),
      BadRequestHttpException::class,
    ];
  }

  #[Test]
  public function testItReturnsTheSingleFacilityRegister(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetFacilityComplianceQuery $query): bool => self::ORGANIZATION_ID === $query->organizationId
        && self::FACILITY_ID === $query->facilityId
        && self::USER_ID === $query->userId))
      ->willReturn($this->facilityResult());

    $output = $this->createProvider($queryBus)->provide(new Get(), $this->uriVariables());

    self::assertSame(self::ORGANIZATION_ID, $output->organizationId);
    self::assertSame(self::FACILITY_ID, $output->facilityId);
    self::assertSame('non_compliant', $output->organizationStatus);
    self::assertCount(1, $output->facilities);
    self::assertSame('Bâtiment A', $output->facilities[0]['name']);
  }

  #[Test]
  public function testTheTotalsReflectThatFacilityAlone(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($this->facilityResult());

    $output = $this->createProvider($queryBus)->provide(new Get(), $this->uriVariables());

    self::assertSame(5, $output->totals['totalEquipmentCount']);
    self::assertSame(4, $output->totals['trackedEquipmentCount']);
    self::assertSame(50.0, $output->totals['complianceRate']);
    self::assertSame(1, $output->totals['openCriticalNonConformityCount']);
  }

  #[Test]
  public function testItRefusesAnUnauthenticatedCaller(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetFacilityComplianceProvider(
      queryBus: $queryBus,
      outputFactory: new ComplianceSummaryOutputFactory(),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $provider->provide(new Get(), $this->uriVariables());
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('incompleteUriVariablesProvider')]
  public function testItReportsAnIncompleteRouteAsNotFound(array $uriVariables): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Facility not found.');

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

    $this->createProvider($queryBus)->provide(new Get(), $this->uriVariables());
  }

  #[Test]
  public function testItRethrowsAnUnrecognisedFailure(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('database is down'));

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('database is down');

    $this->createProvider($queryBus)->provide(new Get(), $this->uriVariables());
  }

  /**
   * @return array<string, string>
   */
  private function uriVariables(): array
  {
    return ['organizationId' => self::ORGANIZATION_ID, 'facilityId' => self::FACILITY_ID];
  }

  private function createProvider(QueryBusPort $queryBus): GetFacilityComplianceProvider
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

    return new GetFacilityComplianceProvider(
      queryBus: $queryBus,
      outputFactory: new ComplianceSummaryOutputFactory(),
      security: $security,
    );
  }

  private function facilityResult(): GetFacilityComplianceResult
  {
    return new GetFacilityComplianceResult(
      generatedAt: '2026-06-01T08:00:00+00:00',
      facility: new FacilityComplianceView(
        facilityId: self::FACILITY_ID,
        name: 'Bâtiment A',
        type: 'building',
        parentFacilityId: null,
        path: 'Site principal / Bâtiment A',
        status: ComplianceStatus::NON_COMPLIANT,
        totalEquipmentCount: 5,
        activeEquipmentCount: 5,
        upToDateEquipmentCount: 2,
        dueSoonEquipmentCount: 1,
        overdueEquipmentCount: 1,
        unscheduledEquipmentCount: 1,
        openLowNonConformityCount: 0,
        openMediumNonConformityCount: 0,
        openHighNonConformityCount: 0,
        openCriticalNonConformityCount: 1,
        lastInspectionAt: null,
      ),
    );
  }
}
