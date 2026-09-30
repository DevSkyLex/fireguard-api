<?php

declare(strict_types=1);

namespace Automation\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/** Output AutomationPolicyOutput. Effective rule and management capability. */
final readonly class AutomationPolicyOutput
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Returns the effective automation rule state and whether the caller can manage it.
   *
   * @access public
   *
   * @param string $id identifier of the organization policy
   * @param string $ruleKey key of the automation rule represented by this output
   * @param bool $enabled whether the rule is enabled in the effective policy
   * @param bool $canManage whether the caller may change this policy
   *
   * @return void
   */
  public function __construct(#[ApiProperty(identifier: true)] public string $id, public string $ruleKey, public bool $enabled, public bool $canManage)
  {
  }
  // #endregion
}
