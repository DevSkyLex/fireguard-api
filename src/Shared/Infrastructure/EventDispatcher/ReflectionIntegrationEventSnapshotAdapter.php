<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventDispatcher;

use ReflectionClass;
use ReflectionNamedType;
use Shared\Application\Port\Outbound\Event\IntegrationEventSnapshotPort;
use Stringable;

/**
 * Class ReflectionIntegrationEventSnapshotAdapter
 *
 * Preserves constructor-generated timestamps and private legacy identifiers when copying immutable snapshots.
 *
 * @category Adapter
 */
final readonly class ReflectionIntegrationEventSnapshotAdapter implements IntegrationEventSnapshotPort
{
  // #region Methods
  /**
   * Method copy
   *
   * Initializes the owner-approved counterpart directly from persisted properties without rerunning its constructor.
   *
   * @access public
   *
   * @template T of object
   *
   * @param object $event the original snapshot
   * @param class-string<T> $contract its owner-approved public counterpart
   *
   * @return T the copied public event
   */
  public function copy(object $event, string $contract): object
  {
    $source = new ReflectionClass($event);
    $target = new ReflectionClass($contract);
    $snapshot = $target->newInstanceWithoutConstructor();

    foreach ($target->getProperties() as $property) {
      if ($property->isStatic()) {
        continue;
      }
      $value = $source->getProperty($property->getName())->getValue($event);
      $type = $property->getType();
      if ($type instanceof ReflectionNamedType && 'string' === $type->getName() && $value instanceof Stringable) {
        $value = (string) $value;
      }
      $property->setValue($snapshot, $value);
    }

    return $snapshot;
  }
  // #endregion
}
