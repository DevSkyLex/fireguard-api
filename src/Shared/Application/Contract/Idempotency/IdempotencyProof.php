<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Idempotency;

use DateTimeImmutable;

/**
 * Class IdempotencyProof
 *
 * Publishes authenticated non-secret scope facts before result decryption.
 *
 * @category Contract
 */
final readonly class IdempotencyProof
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param array<string, string> $metadata verified identifiers, revisions and permission facts
   * @param DateTimeImmutable $expiresAt absolute first-completion replay deadline
   *
   * @return void
   */
  public function __construct(public array $metadata, public DateTimeImmutable $expiresAt)
  {
  }
  // #endregion
}
