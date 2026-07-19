<?php

declare(strict_types=1);

namespace Messaging\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception MessagingSubjectNotFoundException.
 *
 * Raised when a conversation subject (facility, equipment, intervention, or
 * non-conformity) does not exist, or does not belong to the requested
 * organization — enforced through
 * {@see \Messaging\Application\Service\MessagingSubjectResolverRegistry}.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessagingSubjectNotFoundException extends RuntimeException
{
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $subjectType the subject type value
   * @param string $subjectId the subject id value
   *
   * @return self the with id result
   */
  public static function withId(string $subjectType, string $subjectId): self
  {
    return new self(sprintf('%s subject with ID "%s" not found.', $subjectType, $subjectId));
  }
}
