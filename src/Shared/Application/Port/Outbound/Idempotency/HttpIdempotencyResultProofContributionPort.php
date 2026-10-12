<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Message\ResultMessage;

/**
 * Interface HttpIdempotencyResultProofContributionPort
 *
 * Lets an operation owner verify and associate its committed result with nonsecret lifecycle identities.
 *
 * @category Port
 */
interface HttpIdempotencyResultProofContributionPort
{
  // #region Methods
  /**
   * Method supports
   *
   * @access public
   *
   * @param HttpIdempotencyOperation $operation explicitly declared owning operation
   *
   * @return bool whether this owner contributes final result proof
   */
  public function supports(HttpIdempotencyOperation $operation): bool;

  /**
   * Method proofForResult
   *
   * Runs after owner persistence flush and before the receipt's final commit.
   * Verified existing scope/security facts may be repeated identically, never overwritten.
   *
   * @access public
   *
   * @param HttpIdempotencyOperation $operation the declared owning operation
   * @param ResultMessage $result immutable validated primary result
   * @param array<string, string> $signedInitialFacts verified authority and result facts to preserve
   *
   * @return array<string, string> new nonsecret owner-verified facts or identical existing facts
   */
  public function proofForResult(HttpIdempotencyOperation $operation, ResultMessage $result, array $signedInitialFacts): array;
  // #endregion
}
