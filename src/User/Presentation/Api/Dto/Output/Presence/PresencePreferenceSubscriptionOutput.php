<?php

declare(strict_types=1);

namespace User\Presentation\Api\Dto\Output\Presence;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * DTO PresencePreferenceSubscriptionOutput.
 *
 * @category DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PresencePreferenceSubscriptionOutput
{
  #[Groups(['presence-preference:read'])]
  public string $topic = '';

  #[Groups(['presence-preference:read'])]
  public string $token = '';

  #[Groups(['presence-preference:read'])]
  public string $expiresAt = '';
}
