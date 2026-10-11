<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventDispatcher;

use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use Shared\Application\Port\Outbound\Event\IntegrationEventSnapshotPort;
use Stringable;

use function array_key_exists;
use function get_object_vars;
use function is_callable;

/**
 * Class ReflectionIntegrationEventSnapshotAdapter
 *
 * Restores immutable snapshots through their typed constructors and public legacy accessors.
 *
 * @category Adapter
 */
final readonly class ReflectionIntegrationEventSnapshotAdapter implements IntegrationEventSnapshotPort
{
  // #region Methods
  /**
   * Method copy
   *
   * Supplies persisted state to the owner-approved constructor, including its original occurrence time.
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
    $constructor = new ReflectionClass($contract)->getConstructor();
    if (null === $constructor) {
      throw new LogicException('Integration event contracts require a typed restoration constructor.');
    }
    $fields = get_object_vars($event);
    $arguments = [];
    foreach ($constructor->getParameters() as $parameter) {
      $arguments[] = $this->argument($event, $fields, $parameter);
    }

    return new $contract(...$arguments);
  }

  /**
   * Method argument
   *
   * Reads public snapshot facts or their public getter without bypassing property visibility.
   *
   * @access private
   *
   * @param object $event original deserialized snapshot
   * @param array<string, mixed> $fields public persisted fields
   * @param ReflectionParameter $parameter the contract's typed constructor parameter
   *
   * @return mixed the original value with an explicitly scalarized identifier when required
   */
  private function argument(object $event, array $fields, ReflectionParameter $parameter): mixed
  {
    $name = $parameter->getName();
    $accessor = [$event, $name];
    if (array_key_exists($name, $fields)) {
      $value = $fields[$name];
    } elseif (is_callable($accessor)) {
      $value = $accessor();
    } else {
      throw new LogicException('Integration event snapshot has no public value for ' . $name . '.');
    }
    $type = $parameter->getType();

    return $type instanceof ReflectionNamedType && 'string' === $type->getName() && $value instanceof Stringable ? (string) $value : $value;
  }
  // #endregion
}
