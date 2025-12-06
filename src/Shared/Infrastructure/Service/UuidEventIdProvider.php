<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Service;

use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Domain\Service\EventIdProvider;
use Shared\Domain\ValueObject\Uuid;

/**
 * Service UuidEventIdProvider
 * @final
 *
 * Implementation of EventIdProvider using UuidGeneratorPort.
 *
 * @category Service
 * @package Shared\Infrastructure\Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UuidEventIdProvider implements EventIdProvider
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param UuidGeneratorPort $uuidGenerator The UUID generator.
   */
  public function __construct(
    private UuidGeneratorPort $uuidGenerator,
  ) {}
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
   */
  public function nextEventId(): Uuid
  {
    return new Uuid($this->uuidGenerator->generate());
  }
  //#endregion
}
