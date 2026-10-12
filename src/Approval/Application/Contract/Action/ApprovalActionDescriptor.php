<?php

declare(strict_types=1);

namespace Approval\Application\Contract\Action;

use InvalidArgumentException;

/**
 * Class ApprovalActionDescriptor
 *
 * Publishes contributor-owned metadata and the default severity threshold.
 * All contributed actions remain disabled until explicitly enabled by an organization.
 *
 * @category Contract
 */
final readonly class ApprovalActionDescriptor
{
  // #region Constructor
  /**
   * Constructor
   *
   * @access public
   *
   * @param string $value the stable action identifier
   * @param string $label the public display label
   * @param ?string $defaultMinSeverity the threshold when an enabled rule omits it
   */
  public function __construct(
    public string $value,
    public string $label,
    public ?string $defaultMinSeverity = null,
  ) {
    new ApprovalActionKey($value);
    if ('' === $label) {
      throw new InvalidArgumentException('An approval action label is required.');
    }
  }
  // #endregion
}
