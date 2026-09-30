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
  // #region Properties
  /**
   * Property doNotDisturb
   */
  #[Groups(['presence-preference:read'])]
  public bool $doNotDisturb = false;

  /**
   * Property revision
   */
  #[Groups(['presence-preference:read'])]
  public int $revision = 0;

  /**
   * Property invisible
   */
  #[Groups(['presence-preference:read'])]
  public bool $invisible = false;
  // #endregion
}
