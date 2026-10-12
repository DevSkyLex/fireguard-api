<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound\Idempotency;

use Shared\Application\Message\{CommandMessage, QueryMessage, ResultMessage};

/**
 * Port HttpIdempotencyResponseProjectionPort.
 *
 * Owners construct opaque read messages for outputs that their processor first reads after the command commits.
 * A contributor only handles its own messages; consumers never inspect foreign private message types.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface HttpIdempotencyResponseProjectionPort
{
  // #region Methods
  /**
   * Reports the exact operations whose output reads this owner constructs.
   *
   * @since 1.0.0
   *
   * @param string $operation the declared API operation name
   *
   * @return bool whether this owner contributes the operation's response reads
   */
  public function supports(string $operation): bool;

  /**
   * Constructs bounded exact output queries while the primary owning transaction is still active.
   *
   * @since 1.0.0
   *
   * @param string $operation the declared API operation name
   * @param CommandMessage $command the exact route-primary message
   * @param ResultMessage $result the first execution's primary result
   *
   * @return list<QueryMessage> opaque owner messages declared in the operation's projection schema
   */
  public function queries(string $operation, CommandMessage $command, ResultMessage $result): array;
  // #endregion
}
