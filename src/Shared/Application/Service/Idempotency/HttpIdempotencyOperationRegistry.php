<?php

declare(strict_types=1);

namespace Shared\Application\Service\Idempotency;

use BackedEnum;
use DateTimeImmutable;
use InvalidArgumentException;
use ReflectionClass;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Message\{QueryMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

use function class_exists;
use function in_array;
use function is_a;
use function sprintf;

/**
 * Class HttpIdempotencyOperationRegistry
 *
 * Materializes explicit owner operation manifests and rejects duplicate route ownership during construction.
 *
 * @category Service
 */
final readonly class HttpIdempotencyOperationRegistry
{
  // #region Properties
  /**
   * Property operations
   *
   * @var array<string, HttpIdempotencyOperation> explicit route ownership
   */
  private array $operations;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param iterable<HttpIdempotencyOperationContributionPort> $contributors module-owned operation manifests
   *
   * @return void
   */
  public function __construct(iterable $contributors)
  {
    $operations = [];
    foreach ($contributors as $contributor) {
      foreach ($contributor::definitions() as $operation) {
        if (isset($operations[$operation->name])) {
          throw new InvalidArgumentException(sprintf('Duplicate HTTP idempotency operation "%s".', $operation->name));
        }
        $this->validate($operation);
        $operations[$operation->name] = $operation;
      }
    }
    $this->operations = $operations;
  }
  // #endregion

  // #region Methods
  /**
   * Method find
   *
   * @access public
   *
   * @param string $name the API operation name
   *
   * @return HttpIdempotencyOperation|null its declared ownership, or null when not yet covered
   */
  public function find(string $name): ?HttpIdempotencyOperation
  {
    return $this->operations[$name] ?? null;
  }

  /**
   * Method all
   *
   * @access public
   *
   * @return array<string, HttpIdempotencyOperation> the complete declared operation catalog
   */
  public function all(): array
  {
    return $this->operations;
  }

  /**
   * Method validate
   *
   * Validates explicit result and query schemas before requests can reserve a mutation.
   *
   * @access private
   *
   * @param HttpIdempotencyOperation $operation the owner manifest declaration
   *
   * @return void
   */
  private function validate(HttpIdempotencyOperation $operation): void
  {
    $this->schema($operation->resultClass);
    foreach ($operation->contractClasses as $class) {
      $this->schema($class);
    }
    $queries = [];
    foreach ($operation->queryProjections as $projection) {
      if (isset($queries[$projection->queryClass]) || !self::message($projection->queryClass, QueryMessage::class) || !self::message($projection->resultClass, ResultMessage::class)) {
        throw new InvalidArgumentException(sprintf('HTTP idempotency query projection "%s" is invalid or duplicated.', $operation->name));
      }
      $queries[$projection->queryClass] = true;
      $this->schema($projection->resultClass);
      foreach ($projection->contractClasses as $class) {
        $this->schema($class);
      }
    }
    $fresh = [];
    foreach ($operation->freshAuthorizationQueries as $query) {
      if (isset($queries[$query]) || isset($fresh[$query]) || !self::message($query, QueryMessage::class)) {
        throw new InvalidArgumentException(sprintf('HTTP idempotency authorization query "%s" is invalid, duplicated or captured.', $operation->name));
      }
      $fresh[$query] = true;
    }
  }

  /**
   * Method schema
   *
   * @access private
   *
   * @param string $class the explicitly declared result contract
   *
   * @return void
   */
  private function schema(string $class): void
  {
    if (!class_exists($class)) {
      throw new InvalidArgumentException('HTTP idempotency result schema class is missing.');
    }
    $reflection = new ReflectionClass($class);
    if (!in_array($class, [DateTimeImmutable::class], true) && !$reflection->isReadOnly() && !is_a($class, BackedEnum::class, true)) {
      throw new InvalidArgumentException(sprintf('HTTP idempotency result contract "%s" must be immutable.', $class));
    }
  }

  /**
   * Method message
   *
   * @access private
   *
   * @param string $class the configured message type
   * @param class-string $contract the required public message contract
   *
   * @return bool whether the runtime declaration implements its contract
   */
  private static function message(string $class, string $contract): bool
  {
    return is_a($class, $contract, true);
  }
  // #endregion
}
