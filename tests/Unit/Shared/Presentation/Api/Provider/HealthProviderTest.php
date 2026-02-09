<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Provider;

use ApiPlatform\Metadata\Get;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\UseCase\Query\Health\{HealthCheckQuery, HealthCheckResult};
use Shared\Presentation\Api\Dto\Output\HealthOutput;
use Shared\Presentation\Api\Provider\HealthProvider;

/**
 * Test HealthProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(HealthProvider::class)]
final class HealthProviderTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testProvideMapsHealthResult(): void
  {
    $result = new HealthCheckResult(
      status: HealthCheckResult::STATUS_HEALTHY,
      database: true,
      cache: false,
      version: '1.2.3',
      timestamp: '2024-01-01T00:00:00+00:00',
    );

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(HealthCheckQuery::class))
      ->willReturn($result);

    $provider = new HealthProvider(queryBus: $queryBus);

    $output = $provider->provide(new Get());

    self::assertInstanceOf(HealthOutput::class, $output);
    self::assertSame('healthy', $output->status);
    self::assertFalse($output->cache);
    self::assertSame('1.2.3', $output->version);
  }
  // #endregion
}
