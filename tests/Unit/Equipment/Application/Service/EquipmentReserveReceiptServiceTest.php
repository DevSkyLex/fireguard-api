<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\Service;

use Equipment\Application\Contract\Procurement\EquipmentReserveReceiptRequest;
use Equipment\Application\Port\Outbound\EquipmentTypeCatalogPort;
use Equipment\Application\Service\EquipmentReserveReceiptService;
use Equipment\Domain\Model\EquipmentTypeCatalog\EquipmentTypeDefinition;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Contract\Quota\{OrganizationQuotaExceededException,OrganizationQuotaResource};
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationQuotaPort};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Domain\Exception\InvalidValueException;

/** Batch refusal occurs before any equipment creation. @category Unit Tests */
final class EquipmentReserveReceiptServiceTest extends TestCase
{
  private const string ORG = 'beb80000-0000-4000-8000-000000000001';

  private const string ACTOR = 'beb80000-0000-4000-8000-000000000002';

  #[Test]
  public function quotaExhaustionKeepsTheWholeBatchPendingWithoutCreatingAnOrphan(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())->method('assertCanAddMultiple')->with(self::ORG, OrganizationQuotaResource::EQUIPMENT, 2)->willThrowException(OrganizationQuotaExceededException::forResource('equipment', 1));
    $result = $this->service($commands, $quota)->reserve(new EquipmentReserveReceiptRequest(self::ORG, self::ACTOR, 'fire_extinguisher', ['name' => 'Reserve'], 2));
    self::assertSame([], $result->equipmentIds);
    self::assertSame('quota_exceeded', $result->blockedReason);
  }

  #[Test]
  public function equipmentPermissionRevocationKeepsPhysicalReceiptPendingWithoutTouchingQuota(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAddMultiple');
    $result = $this->service($commands, $quota, OrganizationAccessDecision::MISSING_PERMISSION)->reserve(new EquipmentReserveReceiptRequest(self::ORG, self::ACTOR, 'fire_extinguisher', [], 1));
    self::assertSame('missing_equipment_permission', $result->blockedReason);
    self::assertSame([], $result->equipmentIds);
  }

  #[Test]
  public function archivedEquipmentTypeCannotCreateNewIndividualizedReserve(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAddMultiple');
    $result = $this->service($commands, $quota, archived:true)->reserve(new EquipmentReserveReceiptRequest(self::ORG, self::ACTOR, 'fire_extinguisher', [], 1));
    self::assertSame('unavailable_type', $result->blockedReason);
  }

  #[Test]
  public function sharedTemplatesCannotRepeatUniquePatrimonialIdentities(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAddMultiple');
    $this->expectException(InvalidValueException::class);
    $this->service($commands, $quota)->reserve(new EquipmentReserveReceiptRequest(self::ORG, self::ACTOR, 'fire_extinguisher', ['assetCode' => 'SAME'], 2));
  }

  #[Test]
  #[DataProvider('invalidIdentifiers')]
  public function invalidIdentifiersAreRejectedBeforeAuthorizationOrReserveCreation(string $organizationId, string $actorId): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $transactions = $this->createMock(TransactionManagerPort::class);
    $transactions->expects(self::never())->method('transactional');
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAddMultiple');
    $catalog = $this->createMock(EquipmentTypeCatalogPort::class);
    $catalog->expects(self::never())->method('find');
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('resolveAccess');

    $this->expectException(InvalidValueException::class);
    new EquipmentReserveReceiptService($commands, $transactions, $quota, $catalog, $authorization)
      ->reserve(new EquipmentReserveReceiptRequest($organizationId, $actorId, 'fire_extinguisher', [], 1));
  }

  /**
   * @return iterable<string, array{string, string}>
   */
  public static function invalidIdentifiers(): iterable
  {
    yield 'organization' => ['invalid-organization', self::ACTOR];
    yield 'actor' => [self::ORG, 'invalid-actor'];
  }

  private function service(CommandBusPort $commands, OrganizationQuotaPort $quota, OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED, bool $archived = false): EquipmentReserveReceiptService
  {
    $catalog = $this->createStub(EquipmentTypeCatalogPort::class);
    $catalog->method('find')->willReturn(new EquipmentTypeDefinition('fire_extinguisher', 'Extinguisher', 'fire', $archived));
    $auth = $this->createStub(OrganizationAuthorizationPort::class);
    $auth->method('resolveAccess')->willReturn($decision);
    $tx = $this->createStub(TransactionManagerPort::class);
    $tx->method('transactional')->willReturnCallback(static fn (callable $call): mixed => $call());

    return new EquipmentReserveReceiptService($commands, $tx, $quota, $catalog, $auth);
  }
}
