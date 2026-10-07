<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Application\UseCase\Rate;

use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use MaintenanceCost\Application\Port\Outbound\Rate\MaintenanceRateStorePort;
use MaintenanceCost\Application\Service\MaintenanceCostAccessGuard;
use MaintenanceCost\Application\UseCase\Command\Rate\CreateMaintenanceRate\{CreateMaintenanceRateCommand, CreateMaintenanceRateHandler};
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Contract\Workforce\OrganizationWorkforceMemberProfile;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\UuidGeneratorPort;

/** Appending rates never silently replaces an effective rate or reuses a request for different parameters. */
final class CreateMaintenanceRateHandlerTest extends TestCase
{
  private const string ORG = '650e8400-e29b-41d4-a716-448040000001';

  private const string MEMBER = '650e8400-e29b-41d4-a716-448040000004';

  private const string CLIENT = '650e8400-e29b-41d4-a716-448040000010';

  private const string RATE = '650e8400-e29b-41d4-a716-448040000011';

  #[Test]
  public function appendsCanonicalExactAmountForAScopedMember(): void
  {
    $store = $this->store();
    $store->expects(self::once())->method('findByClientId')->with(self::ORG, self::CLIENT)->willReturn(null);
    $store->expects(self::once())->method('findByEffectiveDate')->with(self::ORG, self::MEMBER, '2026-01-01')->willReturn(null);
    $store->expects(self::once())->method('append')->with(self::ORG, self::CLIENT, self::callback(static fn (MaintenanceRateSnapshot $rate): bool => self::RATE === $rate->id && '42.000000' === $rate->hourlyAmount && 'EUR' === $rate->currency));
    $ids = $this->createMock(UuidGeneratorPort::class);
    $ids->expects(self::once())->method('generate')->willReturn(self::RATE);
    $result = $this->handler($store, $ids)($this->command('42'));
    self::assertFalse($result->replayed);
    self::assertSame(self::RATE, $result->rate->id);
  }

  #[Test]
  public function stableReplayKeepsItsOriginalRateIdentity(): void
  {
    $store = $this->store();
    $existing = new MaintenanceRateSnapshot(self::RATE, self::MEMBER, '42.000000', 'EUR', '2026-01-01');
    $store->method('findByClientId')->willReturn($existing);
    $store->expects(self::never())->method('append');
    $store->expects(self::never())->method('findByEffectiveDate');
    $ids = $this->createMock(UuidGeneratorPort::class);
    $ids->expects(self::never())->method('generate');
    $result = $this->handler($store, $ids)($this->command('42.0'));
    self::assertTrue($result->replayed);
    self::assertSame($existing, $result->rate);
  }

  #[Test]
  public function conflictingReplayCannotReplaceAnAmount(): void
  {
    $store = $this->store();
    $store->method('findByClientId')->willReturn(new MaintenanceRateSnapshot(self::RATE, self::MEMBER, '42.000000', 'EUR', '2026-01-01'));
    $store->expects(self::never())->method('append');
    $this->expectException(MaintenanceCostException::class);
    $this->expectExceptionMessage('already used');
    $this->handler($store, $this->createStub(UuidGeneratorPort::class))($this->command('43'));
  }

  #[Test]
  public function anotherRequestCannotReplaceTheSameEffectiveDate(): void
  {
    $store = $this->store();
    $store->method('findByClientId')->willReturn(null);
    $store->method('findByEffectiveDate')->willReturn(new MaintenanceRateSnapshot(self::RATE, self::MEMBER, '42.000000', 'EUR', '2026-01-01'));
    $store->expects(self::never())->method('append');
    $this->expectException(MaintenanceCostException::class);
    $this->expectExceptionMessage('already exists');
    $this->handler($store, $this->createStub(UuidGeneratorPort::class))($this->command('43'));
  }

  #[Test]
  public function rejectsAnotherOrganizationsMemberBeforePersistence(): void
  {
    $store = $this->createMock(MaintenanceRateStorePort::class);
    $store->expects(self::never())->method('synchronized');
    $this->expectException(MaintenanceCostException::class);
    $this->expectExceptionMessage('not found');
    $this->handler($store, $this->createStub(UuidGeneratorPort::class), false)($this->command('42'));
  }

  private function command(string $amount): CreateMaintenanceRateCommand
  {
    return new CreateMaintenanceRateCommand(self::ORG, 'actor', self::MEMBER, $amount, '2026-01-01', self::CLIENT);
  }

  /**
   * @return \PHPUnit\Framework\MockObject\MockObject&MaintenanceRateStorePort
   */
  private function store(): MaintenanceRateStorePort
  {
    $store = $this->createMock(MaintenanceRateStorePort::class);
    $store->method('synchronized')->willReturnCallback(static fn (string $org, callable $work): mixed => $work());

    return $store;
  }

  private function handler(MaintenanceRateStorePort $store, UuidGeneratorPort $ids, bool $scoped = true): CreateMaintenanceRateHandler
  {
    $auth = $this->createMock(OrganizationAuthorizationPort::class);
    $auth->expects(self::once())->method('resolveAccess')->with('actor', self::ORG, 'organization.maintenance_cost.manage')->willReturn(OrganizationAccessDecision::GRANTED);
    $workforce = $this->createMock(OrganizationWorkforceDirectoryPort::class);
    $workforce->expects(self::once())->method('profiles')->with(self::ORG, [self::MEMBER])->willReturn($scoped ? [self::MEMBER => new OrganizationWorkforceMemberProfile(self::MEMBER, 'Technician', null, [])] : []);
    $currency = $this->createStub(MaintenanceCurrencyPort::class);
    $currency->method('forOrganization')->willReturn('EUR');

    return new CreateMaintenanceRateHandler($store, $currency, new MaintenanceCostAccessGuard($auth), $workforce, $ids);
  }
}
