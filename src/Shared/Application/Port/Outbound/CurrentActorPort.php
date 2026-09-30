<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/** Port CurrentActorPort. Identifies the initiating user without persisting credentials. */
interface CurrentActorPort
{
  // #region Methods
  /**
   * Method userId
   *
   * Returns the current actor user identifier, or null when the caller has no user identity.
   *
   * @access public
   *
   * @return ?string the current actor user identifier, or null for a non-user actor
   */
  public function userId(): ?string;
  // #endregion
}
