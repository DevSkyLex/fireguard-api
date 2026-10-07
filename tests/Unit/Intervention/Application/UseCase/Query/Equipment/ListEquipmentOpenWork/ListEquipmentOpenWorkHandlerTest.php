<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Equipment\ListEquipmentOpenWork;

use Intervention\Application\Contract\Resource\InterventionEquipmentWork;
use Intervention\Application\Port\Outbound\{InterventionEquipmentWorkPort, InterventionResourceGatewayPort};
use Intervention\Application\UseCase\Query\Equipment\ListEquipmentOpenWork\{ListEquipmentOpenWorkHandler, ListEquipmentOpenWorkQuery};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Intervention\Domain\ValueObject\InterventionResourceType;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\TestCase;

/** Verifies both organization entitlement and equipment ownership before work is disclosed. */
final class ListEquipmentOpenWorkHandlerTest extends TestCase
{
  public function testAuthorizedEquipmentReturnsExactWorkProjection(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->expects(self::once())->method('resourceBelongsToOrganization')->with(InterventionResourceType::EQUIPMENT, 'equipment', 'organization')->willReturn(true);
    $item = new InterventionEquipmentWork('order', 42, 'Repair', 'in_progress', 'task', 'repair', 'planned');
    $work = $this->createMock(InterventionEquipmentWorkPort::class);
    $work->expects(self::once())->method('findOpenWork')->with('organization', 'equipment')->willReturn([$item]);
    self::assertSame([$item], new ListEquipmentOpenWorkHandler($authorization, $resources, $work)(new ListEquipmentOpenWorkQuery('user', 'organization', 'equipment'))->items);
  }

  public function testOutsiderDoesNotReachEquipmentOrWorkReads(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->expects(self::never())->method('resourceBelongsToOrganization');
    $work = $this->createMock(InterventionEquipmentWorkPort::class);
    $work->expects(self::never())->method('findOpenWork');
    $this->expectException(InterventionNotFoundException::class);
    new ListEquipmentOpenWorkHandler($authorization, $resources, $work)(new ListEquipmentOpenWorkQuery('user', 'organization', 'equipment'));
  }

  public function testMemberWithoutReadPermissionIsDenied(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);
    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->expects(self::never())->method('resourceBelongsToOrganization');
    $work = $this->createMock(InterventionEquipmentWorkPort::class);
    $work->expects(self::never())->method('findOpenWork');
    $this->expectException(InterventionAccessDeniedException::class);
    new ListEquipmentOpenWorkHandler($authorization, $resources, $work)(new ListEquipmentOpenWorkQuery('user', 'organization', 'equipment'));
  }

  public function testForeignEquipmentIsHiddenEvenWhenOrganizationReadIsGranted(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('resourceBelongsToOrganization')->willReturn(false);
    $work = $this->createMock(InterventionEquipmentWorkPort::class);
    $work->expects(self::never())->method('findOpenWork');
    $this->expectException(InterventionNotFoundException::class);
    new ListEquipmentOpenWorkHandler($authorization, $resources, $work)(new ListEquipmentOpenWorkQuery('user', 'organization', 'equipment'));
  }
}
