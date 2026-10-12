<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application\UseCase\Query\Health;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\HealthCheckPort;
use Shared\Application\UseCase\Query\Health\{HealthCheckHandler, HealthCheckQuery, HealthCheckResult};

/**
 * Test HealthCheckHandlerTest.
 *
 * @category Handler Tests
 */
#[CoversClass(className: HealthCheckHandler::class)]
#[CoversClass(className: HealthCheckResult::class)]
#[CoversClass(className: HealthCheckQuery::class)]
final class HealthCheckHandlerTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testHealthyWhenAllDependenciesAreUp(): void
  {
    $healthCheckPort = $this->createStub(HealthCheckPort::class);
    $healthCheckPort->method('checkDatabase')->willReturn(true);
    $healthCheckPort->method('checkCache')->willReturn(true);

    $handler = new HealthCheckHandler($healthCheckPort, \Tests\Support\Shared\ProductConfigFixture::create());
    $result = $handler(new HealthCheckQuery());

    self::assertSame(HealthCheckResult::STATUS_HEALTHY, $result->status);
    self::assertTrue($result->database);
    self::assertTrue($result->cache);
    self::assertNotEmpty($result->timestamp);
    self::assertSame('1.0.0', $result->version);
  }

  #[Test]
  public function testDegradedWhenCacheIsDown(): void
  {
    $healthCheckPort = $this->createStub(HealthCheckPort::class);
    $healthCheckPort->method('checkDatabase')->willReturn(true);
    $healthCheckPort->method('checkCache')->willReturn(false);

    $handler = new HealthCheckHandler($healthCheckPort, \Tests\Support\Shared\ProductConfigFixture::create());
    $result = $handler(new HealthCheckQuery());

    self::assertSame(HealthCheckResult::STATUS_DEGRADED, $result->status);
    self::assertTrue($result->database);
    self::assertFalse($result->cache);
  }

  #[Test]
  public function testUnhealthyWhenDatabaseIsDown(): void
  {
    $healthCheckPort = $this->createStub(HealthCheckPort::class);
    $healthCheckPort->method('checkDatabase')->willReturn(false);
    $healthCheckPort->method('checkCache')->willReturn(true);

    $handler = new HealthCheckHandler($healthCheckPort, \Tests\Support\Shared\ProductConfigFixture::create());
    $result = $handler(new HealthCheckQuery());

    self::assertSame(HealthCheckResult::STATUS_UNHEALTHY, $result->status);
    self::assertFalse($result->database);
    self::assertTrue($result->cache);
  }

  #[Test]
  public function testUnhealthyWhenBothAreDown(): void
  {
    $healthCheckPort = $this->createStub(HealthCheckPort::class);
    $healthCheckPort->method('checkDatabase')->willReturn(false);
    $healthCheckPort->method('checkCache')->willReturn(false);

    $handler = new HealthCheckHandler($healthCheckPort, \Tests\Support\Shared\ProductConfigFixture::create());
    $result = $handler(new HealthCheckQuery());

    self::assertSame(HealthCheckResult::STATUS_UNHEALTHY, $result->status);
    self::assertFalse($result->database);
    self::assertFalse($result->cache);
  }

  #[Test]
  public function testQueryIncludeDetailsProperty(): void
  {
    $query = new HealthCheckQuery(includeDetails: true);

    self::assertTrue($query->includeDetails);
  }

  #[Test]
  public function testQueryDefaultIncludeDetailsIsFalse(): void
  {
    $query = new HealthCheckQuery();

    self::assertFalse($query->includeDetails);
  }

  #[Test]
  public function testResultFactoryMethods(): void
  {
    $healthy = HealthCheckResult::healthy('1.0.0');
    self::assertSame(HealthCheckResult::STATUS_HEALTHY, $healthy->status);
    self::assertTrue($healthy->database);
    self::assertTrue($healthy->cache);

    $degraded = HealthCheckResult::degraded(database: true, cache: false, version: '1.0.0');
    self::assertSame(HealthCheckResult::STATUS_DEGRADED, $degraded->status);
    self::assertTrue($degraded->database);
    self::assertFalse($degraded->cache);

    $unhealthy = HealthCheckResult::unhealthy(database: false, cache: false, version: '1.0.0');
    self::assertSame(HealthCheckResult::STATUS_UNHEALTHY, $unhealthy->status);
    self::assertFalse($unhealthy->database);
    self::assertFalse($unhealthy->cache);
  }

  #[Test]
  #[DataProvider('dependencyStatuses')]
  public function testItReportsTheConfiguredVersionForEveryHealthStatus(bool $database, bool $cache): void
  {
    $healthCheck = $this->createStub(HealthCheckPort::class);
    $healthCheck->method('checkDatabase')->willReturn($database);
    $healthCheck->method('checkCache')->willReturn($cache);
    $handler = new HealthCheckHandler($healthCheck, \Tests\Support\Shared\ProductConfigFixture::create(['product.version' => '2.4.0']));

    self::assertSame('2.4.0', $handler(new HealthCheckQuery())->version);
  }

  /**
   * @return iterable<string, array{bool, bool}> healthy, degraded and unhealthy dependency combinations
   */
  public static function dependencyStatuses(): iterable
  {
    yield 'healthy' => [true, true];
    yield 'degraded' => [true, false];
    yield 'database unavailable' => [false, true];
    yield 'both unavailable' => [false, false];
  }
  // #endregion
}
