<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Application\UseCase\Currency;

use MaintenanceCost\Application\Contract\Currency\MaintenanceCurrencySnapshot;
use MaintenanceCost\Application\Port\Outbound\Currency\MaintenanceCurrencyStorePort;
use MaintenanceCost\Application\Service\MaintenanceCostAccessGuard;
use MaintenanceCost\Application\UseCase\Command\Currency\ConfigureMaintenanceCurrency\{ConfigureMaintenanceCurrencyCommand, ConfigureMaintenanceCurrencyHandler};
use MaintenanceCost\Application\UseCase\Query\Currency\ReadMaintenanceCurrency\{ReadMaintenanceCurrencyHandler, ReadMaintenanceCurrencyQuery};
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Currency commands serialize their invariant, and queries keep finance permissions independent. */
final class MaintenanceCurrencyHandlersTest extends TestCase
{
  private const string ORG = '650e8400-e29b-41d4-a716-448040000001';

  #[Test]
  public function configuresInsideTheOrganizationLock(): void
  {
    $store = $this->createMock(MaintenanceCurrencyStorePort::class);
    $store->expects(self::once())->method('synchronized')->with(self::ORG, self::isCallable())->willReturnCallback(static fn (string $org, callable $work): mixed => $work());
    $store->expects(self::once())->method('read')->with(self::ORG)->willReturn(new MaintenanceCurrencySnapshot(self::ORG, 'EUR', false));
    $store->expects(self::once())->method('save')->with(self::callback(static fn (MaintenanceCurrencySnapshot $value): bool => 'USD' === $value->currency && !$value->locked));
    $handler = new ConfigureMaintenanceCurrencyHandler($store, $this->access(true));
    self::assertSame('USD', $handler(new ConfigureMaintenanceCurrencyCommand(self::ORG, 'actor', 'USD'))->currency->currency);
  }

  #[Test]
  public function refusesLockedChangesWithoutSaving(): void
  {
    $store = $this->createMock(MaintenanceCurrencyStorePort::class);
    $store->method('synchronized')->willReturnCallback(static fn (string $org, callable $work): mixed => $work());
    $store->method('read')->willReturn(new MaintenanceCurrencySnapshot(self::ORG, 'EUR', true));
    $store->expects(self::never())->method('save');
    $this->expectException(MaintenanceCostException::class);
    (new ConfigureMaintenanceCurrencyHandler($store, $this->access(true)))(new ConfigureMaintenanceCurrencyCommand(self::ORG, 'actor', 'USD'));
  }

  #[Test]
  public function readsWithoutCreatingACurrencySetting(): void
  {
    $store = $this->createMock(MaintenanceCurrencyStorePort::class);
    $store->expects(self::once())->method('read')->with(self::ORG)->willReturn(new MaintenanceCurrencySnapshot(self::ORG, 'EUR', false));
    $store->expects(self::never())->method('save');
    self::assertSame('EUR', (new ReadMaintenanceCurrencyHandler($store, $this->access(false)))(new ReadMaintenanceCurrencyQuery(self::ORG, 'actor'))->currency->currency);
  }

  #[Test]
  public function checksAccessBeforeCurrencyState(): void
  {
    $auth = $this->createMock(OrganizationAuthorizationPort::class);
    $auth->expects(self::once())->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);
    $store = $this->createMock(MaintenanceCurrencyStorePort::class);
    $store->expects(self::never())->method('synchronized');
    $store->expects(self::never())->method('read');
    $this->expectException(MaintenanceCostException::class);
    $this->expectExceptionMessage('not found');
    (new ConfigureMaintenanceCurrencyHandler($store, new MaintenanceCostAccessGuard($auth)))(new ConfigureMaintenanceCurrencyCommand(self::ORG, 'actor', 'USD'));
  }

  private function access(bool $write): MaintenanceCostAccessGuard
  {
    $auth = $this->createMock(OrganizationAuthorizationPort::class);
    $auth->expects(self::once())->method('resolveAccess')->with('actor', self::ORG, $write ? 'organization.maintenance_cost.manage' : 'organization.maintenance_cost.read')->willReturn(OrganizationAccessDecision::GRANTED);

    return new MaintenanceCostAccessGuard($auth);
  }
}
