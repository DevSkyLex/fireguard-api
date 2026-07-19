<?php

declare(strict_types=1);

namespace Assistant\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception AssistantThreadNotFoundException.
 *
 * Also raised when a thread exists but belongs to a different organization,
 * or to a different member, than the requesting actor — information hiding,
 * not a distinct access-denied case (mirrors
 * {@see \Approval\Domain\Exception\ApprovalRequestNotFoundException}). A
 * member may only ever read their OWN assistant threads: there is no
 * shared/organization-wide assistant thread in this design.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantThreadNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the assistant thread identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Assistant thread with ID "%s" not found.', $id));
  }
  // #endregion
}
