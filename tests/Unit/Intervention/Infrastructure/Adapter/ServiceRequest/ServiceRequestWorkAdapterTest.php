<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Adapter\ServiceRequest;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Inbound\EquipmentParkScopePort;
use Intervention\Application\Port\Outbound\{InterventionActivityPort, InterventionWorkflowGatewayPort};
use Intervention\Application\Service\InterventionMemberPolicy;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionConflictException, InterventionNotFoundException};
use Intervention\Infrastructure\Adapter\ServiceRequest\ServiceRequestWorkAdapter;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use PHPUnit\Framework\TestCase;
use ServiceRequest\Application\Contract\Work\ServiceRequestWorkRequest;

/**
 * Class ServiceRequestWorkAdapterTest
 *
 * Proves permission and transaction denials precede all corrective workflow writes.
 *
 * @category Unit Tests
 */
final class ServiceRequestWorkAdapterTest extends TestCase
{
  public function testOutsideScopeReturnsNotFoundWithoutQueryingBusinessRecords(): void
  {
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::never())->method('getConnection');
    $this->expectException(InterventionNotFoundException::class);
    $this->adapter($manager, OrganizationAccessDecision::OUTSIDE_SCOPE)->createOrLink($this->request());
  }

  public function testMissingInterventionPermissionDeniesBeforeAnyWrite(): void
  {
    $manager = $this->createMock(EntityManagerInterface::class);
    $manager->expects(self::never())->method('getConnection');
    $this->expectException(InterventionAccessDeniedException::class);
    $this->adapter($manager, OrganizationAccessDecision::MISSING_PERMISSION)->createOrLink($this->request());
  }

  public function testConversionRequiresTheCallersMainTransaction(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $connection->expects(self::never())->method('executeQuery');
    $manager = $this->createStub(EntityManagerInterface::class);
    $manager->method('getConnection')->willReturn($connection);
    $this->expectException(InterventionConflictException::class);
    $this->adapter($manager, OrganizationAccessDecision::GRANTED)->createOrLink($this->request());
  }

  private function adapter(EntityManagerInterface $manager, OrganizationAccessDecision $decision): ServiceRequestWorkAdapter
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('resolveAccess')->with('actor', 'organization', 'organization.interventions.plan')->willReturn($decision);
    $workflow = $this->createMock(InterventionWorkflowGatewayPort::class);
    $workflow->expects(self::never())->method('mutate');
    $equipment = $this->createMock(EquipmentParkScopePort::class);
    $equipment->expects(self::never())->method('filterIds');
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::never())->method('append');

    return new ServiceRequestWorkAdapter($manager, $workflow, $authorization, $equipment, $activities, new InterventionMemberPolicy($this->createStub(OrganizationMemberRepositoryPort::class)));
  }

  private function request(): ServiceRequestWorkRequest
  {
    return new ServiceRequestWorkRequest('organization', 'request', 'actor', 'equipment', null, 'Repair extinguisher', 'Replace damaged seal', null, null, 'operation');
  }
}
