<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Closure;
use RuntimeException;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Infrastructure\Exception\UuidGenerationException;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Adapter UuidGeneratorAdapter.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UuidGeneratorAdapter implements UuidGeneratorPort
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initialize the UUID generator adapter.
     *
     * @since 1.0.0
     *
     * @param ?Closure $generator the generator to use for UUID generation
     */
    public function __construct(
        private readonly ?Closure $generator = null,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method generate
     * {@inheritDoc}
     *
     * Generate and return a UUID identifier.
     *
     * @since 1.0.0
     *
     * @return string the generated UUID
     */
    public function generate(): string
    {
        try {
            $generator = $this->generator ?? static fn (): Uuid => Uuid::v7();
            $uuid = $generator();

            if (!$uuid instanceof Uuid) {
                throw new RuntimeException('Generator must return a Uuid instance');
            }

            return $uuid->toRfc4122();
        } catch (Throwable $exception) {
            throw UuidGenerationException::dueToRandomFailure(
                previous: $exception
            );
        }
    }
    // #endregion
}
