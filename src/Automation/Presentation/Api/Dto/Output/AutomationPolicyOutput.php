<?php

declare(strict_types=1);

namespace Automation\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/** Output AutomationPolicyOutput. Effective rule and management capability. */
final readonly class AutomationPolicyOutput
{
  public function __construct(#[ApiProperty(identifier: true)] public string $id, public string $ruleKey, public bool $enabled, public bool $canManage)
  {
  }
}
