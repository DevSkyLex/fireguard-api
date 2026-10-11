<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Http;

use function preg_match;

/**
 * Class RevisionPrecondition.
 *
 * Interprets the existing revision ETag syntax without exporting transport types.
 *
 * @category Contract
 */
final readonly class RevisionPrecondition
{
  // #region Methods
  /**
   * Method expectedRevision.
   *
   * Parses the owning mutation's conditional header without trimming or accepting wildcards.
   *
   * @access public
   *
   * @param string|null $ifMatch the conditional header supplied by the transport
   *
   * @return int the expected persisted revision
   *
   * @throws RevisionMissingException when the header is absent or empty
   * @throws RevisionMismatchException when the header does not match the existing ETag syntax
   */
  public static function expectedRevision(?string $ifMatch): int
  {
    if (null === $ifMatch || '' === $ifMatch) {
      throw new RevisionMissingException();
    }
    if (1 !== preg_match('/^"revision-(\d+)"$/', $ifMatch, $matches)) {
      throw new RevisionMismatchException();
    }

    return (int) $matches[1];
  }

  /**
   * Method matches.
   *
   * Compares the parsed condition with the persisted revision.
   *
   * @access public
   *
   * @param int $expectedRevision the parsed conditional revision
   * @param int $revision the current persisted revision
   *
   * @return bool whether the mutation still owns its expected revision
   */
  public static function matches(int $expectedRevision, int $revision): bool
  {
    return $revision === $expectedRevision;
  }
  // #endregion
}
