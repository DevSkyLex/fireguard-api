<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Service;

use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Domain\Service\EventIdProvider;
use Shared\Domain\ValueObject\Uuid;

/**
 * Service UuidEventIdProvider.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UuidEventIdProvider implements EventIdProvider
{
    // #region Constructor
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param UuidGeneratorPort $uuidGenerator the UUID generator
     */
    public function __construct(
        private UuidGeneratorPort $uuidGenerator,
    ) {
    }
    // #endregion

    // #region Methods
    public function nextEventId(): Uuid
    {
        return new Uuid($this->uuidGenerator->generate());
    }
    // #endregion
}
