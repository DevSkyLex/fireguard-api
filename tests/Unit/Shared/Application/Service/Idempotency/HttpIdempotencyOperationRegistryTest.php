<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Application\Service\Idempotency;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\{HttpIdempotencyOperation, HttpIdempotencyQueryProjection};
use Shared\Application\Message\{CommandMessage, QueryMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;
use Shared\Application\Service\Idempotency\HttpIdempotencyOperationRegistry;

/**
 * Class HttpIdempotencyOperationRegistryTest
 *
 * Proves duplicate ownership and unsafe query/result schemas fail before a request can reserve a write.
 *
 * @category Service Tests
 */
#[CoversClass(HttpIdempotencyOperationRegistry::class)]
final class HttpIdempotencyOperationRegistryTest extends TestCase
{
  protected function tearDown(): void
  {
    RegistryTestContribution::$operations = [];
    parent::tearDown();
  }

  #[Test]
  public function duplicateOwnerOperationNamesFailConstruction(): void
  {
    RegistryTestContribution::$operations = [$this->operation()];
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Duplicate HTTP idempotency operation');
    new HttpIdempotencyOperationRegistry([new RegistryTestContribution(), new RegistryTestContribution()]);
  }

  #[Test]
  public function aQueryCannotBeBothSavedProjectionAndFreshAuthorization(): void
  {
    RegistryTestContribution::$operations = [new HttpIdempotencyOperation('owned', 'main', 'bus_owner', RegistryTestCommand::class, RegistryTestResult::class, queryProjections: [new HttpIdempotencyQueryProjection(RegistryTestQuery::class, RegistryTestResult::class)], freshAuthorizationQueries: [RegistryTestQuery::class])];
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('authorization query');
    new HttpIdempotencyOperationRegistry([new RegistryTestContribution()]);
  }

  #[Test]
  public function mutableDeclaredResultsCannotEnterTheCatalog(): void
  {
    RegistryTestContribution::$operations = [new HttpIdempotencyOperation('owned', 'main', 'bus_owner', RegistryTestCommand::class, RegistryMutableResult::class)];
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('must be immutable');
    new HttpIdempotencyOperationRegistry([new RegistryTestContribution()]);
  }

  #[Test]
  public function duplicateQuerySchemasCannotShadowAProcessorPrecondition(): void
  {
    $query = new HttpIdempotencyQueryProjection(RegistryTestQuery::class, RegistryTestResult::class);
    RegistryTestContribution::$operations = [new HttpIdempotencyOperation('owned', 'main', 'bus_owner', RegistryTestCommand::class, RegistryTestResult::class, queryProjections: [$query, $query])];
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('invalid or duplicated');
    new HttpIdempotencyOperationRegistry([new RegistryTestContribution()]);
  }

  private function operation(): HttpIdempotencyOperation
  {
    return new HttpIdempotencyOperation('owned', 'main', 'bus_owner', RegistryTestCommand::class, RegistryTestResult::class);
  }
}

final class RegistryTestContribution implements HttpIdempotencyOperationContributionPort
{
  /**
   * @var list<HttpIdempotencyOperation>
   */
  public static array $operations = [];

  public static function definitions(): array
  {
    return self::$operations;
  }
}

final readonly class RegistryTestCommand implements CommandMessage
{
}

final readonly class RegistryTestQuery implements QueryMessage
{
}

final readonly class RegistryTestResult implements ResultMessage
{
}

final class RegistryMutableResult implements ResultMessage
{
}
