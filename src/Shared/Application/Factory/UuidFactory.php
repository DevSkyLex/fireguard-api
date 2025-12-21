<?php

declare(strict_types=1);

namespace Shared\Application\Factory;

use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Domain\ValueObject\Uuid;

/**
 * Factory UuidFactory.
 *
 * @final
 *
 * Factory for creating UUID-based identifiers.
 * Uses UuidGeneratorPort to maintain hexagonal architecture purity.
 *
 * @category Factory
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
readonly class UuidFactory
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param UuidGeneratorPort $generator the UUID generator port
   */
  public function __construct(
    private UuidGeneratorPort $generator,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new UUID value object of the specified type.
   *
   * @since 1.0.0
   *
   * @template T of Uuid
   *
   * @param class-string<T> $class the UUID class to instantiate
   *
   * @return T the new UUID instance
   */
  public function create(string $class): Uuid
  {
    return new $class($this->generator->generate());
  }

  /**
   * Method generateRaw.
   *
   * Generates a raw UUID string.
   *
   * @since 1.0.0
   *
   * @return string the generated UUID string
   */
  public function generateRaw(): string
  {
    return $this->generator->generate();
  }
  // #endregion
}
