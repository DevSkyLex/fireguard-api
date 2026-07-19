<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Validator\ValidApprovalPolicy;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Constraint ValidApprovalPolicy.
 *
 * Class-level constraint validating the `actionRules` keys of an
 * `UpdateOrganizationApprovalInput` payload against the Approval module's
 * action-type catalog (via `ApprovalActionTypeCatalogPort`) — the Organization
 * domain value object cannot: it never depends on the Approval module.
 *
 * @category Validator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ValidApprovalPolicy extends Constraint
{
  // #region Properties
  /**
   * Property unknownActionTypeMessage.
   */
  public string $unknownActionTypeMessage = 'Unknown approval action type "{{ value }}". Supported types: {{ types }}.';
  // #endregion

  // #region Methods
  /**
   * Method getTargets.
   *
   * @since 1.0.0
   *
   * @return string the constraint target
   */
  public function getTargets(): string
  {
    return self::CLASS_CONSTRAINT;
  }
  // #endregion
}
