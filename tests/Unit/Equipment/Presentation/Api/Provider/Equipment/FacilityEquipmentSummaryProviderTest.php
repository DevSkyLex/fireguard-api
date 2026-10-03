<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Query\Equipment\GetFacilityEquipmentSummary\{GetFacilityEquipmentSummaryQuery, GetFacilityEquipmentSummaryResult};
use Equipment\Presentation\Api\Provider\Equipment\FacilityEquipmentSummaryProvider;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

/**
 * Test FacilityEquipmentSummaryProviderTest.
 *
 * @category Unit Tests
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityEquipmentSummaryProvider::class)]
final class FacilityEquipmentSummaryProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function mapsTheRequestedDirectScopeAndCompleteCounts(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(new GetFacilityEquipmentSummaryQuery('organization', 'facility', false))
      ->willReturn(new GetFacilityEquipmentSummaryResult('direct', 5, ['in_stock' => 1, 'operational' => 2, 'under_maintenance' => 1, 'decommissioned' => 1], 2));
    $output = $this->provider($queries, OrganizationAccessDecision::GRANTED, '/summary?includeDescendants=false')->provide(new Get(), ['organizationId' => 'organization', 'facilityId' => 'facility']);
    self::assertSame('direct', $output->scope);
    self::assertSame(5, $output->totalItems);
    self::assertSame(2, $output->needingAttentionCount);
    self::assertSame(1, $output->byStatus['decommissioned']);
  }

  #[Test]
  public function missingEquipmentReadPermissionIsForbidden(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $this->expectException(AccessDeniedHttpException::class);
    $this->provider($queries, OrganizationAccessDecision::MISSING_PERMISSION)->provide(new Get(), ['organizationId' => 'organization', 'facilityId' => 'facility']);
  }

  #[Test]
  public function outsideOrganizationIsHidden(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $this->expectException(NotFoundHttpException::class);
    $this->provider($queries, OrganizationAccessDecision::OUTSIDE_SCOPE)->provide(new Get(), ['organizationId' => 'organization', 'facilityId' => 'facility']);
  }

  #[Test]
  public function invalidBooleanIsRejectedBeforeDispatch(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $this->expectException(\Symfony\Component\HttpFoundation\Exception\BadRequestException::class);
    $this->provider($queries, OrganizationAccessDecision::GRANTED, '/summary?includeDescendants=invalid')->provide(new Get(), ['organizationId' => 'organization', 'facilityId' => 'facility']);
  }

  private function provider(QueryBusPort $queries, OrganizationAccessDecision $decision, string $url = '/summary'): FacilityEquipmentSummaryProvider
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->with('user', 'organization', 'organization.equipment.read')->willReturn($decision);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('user', 'user@example.com', 'password', ['ROLE_USER']));
    $requests = new RequestStack();
    $requests->push(Request::create($url));

    return new FacilityEquipmentSummaryProvider($queries, $authorization, $security, $requests);
  }
  // #endregion
}
