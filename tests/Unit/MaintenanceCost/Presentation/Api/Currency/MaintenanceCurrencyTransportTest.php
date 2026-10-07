<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Presentation\Api\Currency;

use ApiPlatform\Metadata\{Get, Patch};
use MaintenanceCost\Application\Contract\Currency\MaintenanceCurrencySnapshot;
use MaintenanceCost\Application\UseCase\Command\Currency\ConfigureMaintenanceCurrency\{ConfigureMaintenanceCurrencyCommand, ConfigureMaintenanceCurrencyResult};
use MaintenanceCost\Application\UseCase\Query\Currency\ReadMaintenanceCurrency\{ReadMaintenanceCurrencyQuery, ReadMaintenanceCurrencyResult};
use MaintenanceCost\Presentation\Api\Dto\Input\Currency\ConfigureMaintenanceCurrencyInput;
use MaintenanceCost\Presentation\Api\Processor\Currency\ConfigureMaintenanceCurrencyProcessor;
use MaintenanceCost\Presentation\Api\Provider\Currency\MaintenanceCurrencyProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Currency transport delegates decisions to bus-dispatched use cases. */
final class MaintenanceCurrencyTransportTest extends TestCase
{
  #[Test]
  public function dispatchesTheActorAndOrganizationForConfiguration(): void
  {
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('actor');
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (ConfigureMaintenanceCurrencyCommand $command): bool => 'org' === $command->organizationId && 'actor' === $command->actorUserId && 'USD' === $command->currency))->willReturn(new ConfigureMaintenanceCurrencyResult(new MaintenanceCurrencySnapshot('org', 'USD', false)));
    $input = new ConfigureMaintenanceCurrencyInput();
    $input->currency = 'USD';
    $output = new ConfigureMaintenanceCurrencyProcessor($bus, $actor)->process($input, new Patch(), ['organizationId' => 'org']);
    self::assertSame('USD', $output->currency);
    self::assertFalse($output->locked);
  }

  #[Test]
  public function providesTheReadOnlyLockState(): void
  {
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('actor');
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (ReadMaintenanceCurrencyQuery $query): bool => 'org' === $query->organizationId && 'actor' === $query->actorUserId))->willReturn(new ReadMaintenanceCurrencyResult(new MaintenanceCurrencySnapshot('org', 'EUR', true)));
    self::assertTrue(new MaintenanceCurrencyProvider($bus, $actor)->provide(new Get(), ['organizationId' => 'org'])->locked);
  }

  #[Test]
  public function unauthenticatedReadNeverDispatchesAQuery(): void
  {
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn(null);
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::never())->method('ask');
    $this->expectException(AccessDeniedHttpException::class);
    new MaintenanceCurrencyProvider($bus, $actor)->provide(new Get(), ['organizationId' => 'org']);
  }
}
