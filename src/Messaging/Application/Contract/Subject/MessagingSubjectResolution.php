<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Subject;

/**
 * Contract MessagingSubjectResolution.
 *
 * The result of resolving a conversation subject through the
 * `messaging.subject_resolver` tagged-iterator seam: whether the subject
 * exists in the requesting organization, its display label, and the
 * permission required to read it (checked in addition to
 * `organization.messaging.read`).
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingSubjectResolution
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $exists whether the subject exists in the requesting organization
   * @param ?string $label the subject's display label, when it exists
   * @param string $requiredReadPermission the organization permission required to read the subject
   */
  public function __construct(
    public bool $exists,
    public ?string $label,
    public string $requiredReadPermission,
  ) {
  }
  // #endregion
}
