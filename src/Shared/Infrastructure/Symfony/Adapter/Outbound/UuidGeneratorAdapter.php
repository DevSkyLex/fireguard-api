<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Closure;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Infrastructure\Exception\UuidGenerationException;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Adapter UuidGeneratorAdapter
 * @final
 *
 * Adapter generating RFC 4122 version 4 UUIDs.
 *
 * @category Outbound Adapter
 * @package Shared\Infrastructure\Symfony\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UuidGeneratorAdapter implements UuidGeneratorPort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the UUID generator adapter.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ?Closure $generator The generator to use for UUID generation.
   */
  public function __construct(
    private readonly ?Closure $generator = null,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method generate
   * {@inheritDoc}
   *
   * Generate and return a UUID identifier.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The generated UUID.
   */
  public function generate(): string
  {
    try {
      $generator = $this->generator ?? static fn(): Uuid => Uuid::v7();
      $uuid = $generator();

      return $uuid->toRfc4122();
    }
    catch (Throwable $exception) {
      throw UuidGenerationException::dueToRandomFailure(
        previous: $exception
      );
    }
  }
  //#endregion
}
