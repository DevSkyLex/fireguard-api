<?php

declare(strict_types=1);

namespace OAuth\Domain\Model\Client;

use DateTimeImmutable;

/**
 * Lifecycle restored from a persisted OAuth client.
 *
 * @category Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoredClientLifecycle
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted activation and lifecycle timestamps for an OAuth client.
   *
   * @access public
   *
   * @param bool $isActive whether the client is enabled to issue tokens
   * @param DateTimeImmutable $createdAt original creation timestamp
   * @param ?DateTimeImmutable $deletedAt optional deletion timestamp
   *
   * @return void
   */
  public function __construct(
    public bool $isActive,
    public DateTimeImmutable $createdAt,
    public ?DateTimeImmutable $deletedAt,
  ) {
  }
  // #endregion
}
