<?php

declare(strict_types=1);

namespace Shared\Infrastructure\DataFixtures;

use OutOfBoundsException;
use Shared\Application\Contract\Seed\SeedReference;
use Shared\Application\Port\Inbound\SeedReferenceRegistryPort;

/**
 * Class SeedReferenceRegistryAdapter
 *
 * Holds immutable projections for one seed execution and resets between databases or executions.
 *
 * @category Adapter
 */
final class SeedReferenceRegistryAdapter implements SeedReferenceRegistryPort
{
  // #region Properties
  /**
   * @var array<string, SeedReference> completed seed projections
   */
  private array $references = [];
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function get(string $reference): SeedReference
  {
    return $this->references[$reference] ?? throw new OutOfBoundsException('Unknown seed reference: ' . $reference);
  }

  /**
   * Method publish
   *
   * @access public
   *
   * @param array<string, SeedReference> $references immutable completed-step projections
   *
   * @return void
   */
  public function publish(array $references): void
  {
    $this->references = [...$this->references, ...$references];
  }

  /**
   * Method reset
   *
   * @access public
   *
   * @return void
   */
  public function reset(): void
  {
    $this->references = [];
  }
  // #endregion
}
