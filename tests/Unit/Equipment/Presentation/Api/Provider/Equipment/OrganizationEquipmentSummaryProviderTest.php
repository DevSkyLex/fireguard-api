<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Query\Equipment\GetFacilityEquipmentSummary\GetFacilityEquipmentSummaryResult;
use Equipment\Application\UseCase\Query\Equipment\GetOrganizationEquipmentSummary\GetOrganizationEquipmentSummaryQuery;
use Equipment\Presentation\Api\Provider\Equipment\OrganizationEquipmentSummaryProvider;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

final class OrganizationEquipmentSummaryProviderTest extends TestCase
{
  #[Test]
  public function mapsCompleteCountsAndRequestedCustomerFamily(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(new GetOrganizationEquipmentSummaryQuery('org', 'fire', 'customer'))
      ->willReturn(new GetFacilityEquipmentSummaryResult('customer', 7, ['in_stock' => 2, 'operational' => 3, 'under_maintenance' => 1, 'decommissioned' => 1], 2));
    $output = $this->provider($queries, OrganizationAccessDecision::GRANTED)->provide(new Get(), ['organizationId' => 'org']);
    self::assertSame('customer', $output->scope);
    self::assertSame(7, $output->totalItems);
    self::assertSame(1, $output->byStatus['under_maintenance']);
  }

  #[Test]
  public function hidesOutsideScopeWithoutQuerying(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $this->expectException(NotFoundHttpException::class);
    $this->provider($queries, OrganizationAccessDecision::OUTSIDE_SCOPE)->provide(new Get(), ['organizationId' => 'org']);
  }

  #[Test]
  public function deniesMissingReadPermissionWithoutQuerying(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $this->expectException(AccessDeniedHttpException::class);
    $this->provider($queries, OrganizationAccessDecision::MISSING_PERMISSION)->provide(new Get(), ['organizationId' => 'org']);
  }

  private function provider(QueryBusPort $queries, OrganizationAccessDecision $decision): OrganizationEquipmentSummaryProvider
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('user', 'user@example.com', 'password', ['ROLE_USER']));
    $requests = new RequestStack();
    $requests->push(Request::create('/summary?family=fire&customerId=customer'));

    return new OrganizationEquipmentSummaryProvider($queries, $authorization, $security, $requests);
  }
}
