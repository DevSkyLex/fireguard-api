<?php

declare(strict_types=1);

namespace Shared\Application\Factory;

use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Domain\ValueObject\Uuid;

/**
 * Factory UuidFactory
 * @final
 *
 * Factory for creating UUID-based identifiers.
 * Uses UuidGeneratorPort to maintain hexagonal architecture purity.
 *
 * @category Factory
 * @package Shared\Application\Factory
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
readonly class UuidFactory
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param UuidGeneratorPort $generator The UUID generator port.
   */
  public function __construct(
    private UuidGeneratorPort $generator,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method create
   *
   * Creates a new UUID value object of the specified type.
   *
   * @access public
   * @since 1.0.0
   *
   * @template T of Uuid
   * @param class-string<T> $class The UUID class to instantiate.
   *
   * @return T The new UUID instance.
   */
  public function create(string $class): Uuid
  {
    return new $class($this->generator->generate());
  }

  /**
   * Method generateRaw
   *
   * Generates a raw UUID string.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The generated UUID string.
   */
  public function generateRaw(): string
  {
    return $this->generator->generate();
  }
  //#endregion
}
