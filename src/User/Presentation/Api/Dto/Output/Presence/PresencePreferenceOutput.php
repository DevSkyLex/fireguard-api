<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto\Output\Presence;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * DTO PresencePreferenceOutput.
 *
 * @category DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PresencePreferenceOutput
{
  #[Groups(['presence-preference:read'])]
  public bool $doNotDisturb = false;

  #[Groups(['presence-preference:read'])]
  public int $revision = 0;

  #[Groups(['presence-preference:read'])]
  public bool $invisible = false;
}
