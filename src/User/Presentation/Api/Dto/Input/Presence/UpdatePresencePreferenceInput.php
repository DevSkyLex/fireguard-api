<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto\Input\Presence;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * DTO UpdatePresencePreferenceInput.
 *
 * @category DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdatePresencePreferenceInput
{
  // #region Properties
  /**
   * Property doNotDisturb
   */
  #[Groups(['presence-preference:write'])]
  #[Assert\Type('bool')]
  #[ApiProperty(openapiContext: ['type' => 'boolean'])]
  public mixed $doNotDisturb = null;

  /**
   * Property invisible
   */
  #[Groups(['presence-preference:write'])]
  #[Assert\Type('bool')]
  #[ApiProperty(openapiContext: ['type' => 'boolean'])]
  public mixed $invisible = null;
  // #endregion

  /**
   * Require at least one explicit boolean while allowing independent partial updates.
   */
  #[Assert\Callback]
  public function validate(ExecutionContextInterface $context): void
  {
    if (null === $this->doNotDisturb && null === $this->invisible) {
      $context->buildViolation('At least one presence preference boolean is required.')->atPath('doNotDisturb')->addViolation();
    }
  }
}
