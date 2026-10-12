<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Subject;

/**
 * Contract MessagingSubjectDescriptor.
 *
 * Owner-published metadata for a registered subject. The type is a stable
 * persisted key, resourceSegment builds subject IRIs, and the permission is
 * required in addition to the messaging permission for scoped subject access.
 *
 * @category Contract
 */
final readonly class MessagingSubjectDescriptor
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @access public
   *
   * @param string $type the stable subject key
   * @param string $label the subject kind's display label
   * @param string $resourceSegment the descriptive API resource path segment
   * @param string $requiredReadPermission the subject's organization read permission
   */
  public function __construct(
    public string $type,
    public string $label,
    public string $resourceSegment,
    public string $requiredReadPermission,
  ) {
  }
  // #endregion
}
