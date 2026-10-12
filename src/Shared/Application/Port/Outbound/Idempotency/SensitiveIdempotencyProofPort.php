<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

use Shared\Application\Contract\Idempotency\{IdempotencyProof, SensitiveIdempotencyAuthorization};
use Shared\Application\Message\{CommandMessage, ResultMessage};

/**
 * Interface SensitiveIdempotencyProofPort
 *
 * Owners reverify credentials, current state and exact consumed-factor/rotation lineage before secret result decryption. A client key alone never grants access.
 *
 * @category Port
 */
interface SensitiveIdempotencyProofPort
{
  // #region Methods
  /**
   * Method supports
   *
   * @access public
   *
   * @param string $commandClass exact application command type
   *
   * @return bool whether this owner policy handles the command
   */
  public function supports(string $commandClass): bool;

  /**
   * Method authorize
   *
   * Called first for current scope, then with authenticated stored proof before engine replay/decryption. Denials expose no cached identity or credential.
   *
   * @access public
   *
   * @param CommandMessage $message exact owner command
   * @param ?IdempotencyProof $storedProof authenticated non-secret receipt proof, if completed
   *
   * @return SensitiveIdempotencyAuthorization current authorization and logical input
   */
  public function authorize(CommandMessage $message, ?IdempotencyProof $storedProof): SensitiveIdempotencyAuthorization;

  /**
   * Method proofForResult
   *
   * Captures current post-mutation proof inside the final owning transaction, before receipt completion.
   *
   * @access public
   *
   * @param CommandMessage $message exact owner command
   * @param ResultMessage $result application outcome
   *
   * @return array<string, string> non-secret owner proof facts
   */
  public function proofForResult(CommandMessage $message, ResultMessage $result): array;

  /**
   * Method replayResult
   *
   * Reconstructs a verified replay with remaining lifetimes, without extending first-completion deadlines or creating new credentials.
   *
   * @access public
   *
   * @param ResultMessage $result original decoded application outcome
   * @param IdempotencyProof $proof authenticated first-completion facts and deadline
   *
   * @return ResultMessage current safe replay outcome
   */
  public function replayResult(ResultMessage $result, IdempotencyProof $proof): ResultMessage;
  // #endregion
}
