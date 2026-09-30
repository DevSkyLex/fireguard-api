<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Output;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * DTO PresenceSubscriptionOutput.
 *
 * @category DTO
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PresenceSubscriptionOutput
{
  #[Groups(['presence:read'])]
  public string $topic = '';

  #[Groups(['presence:read'])]
  public string $token = '';

  #[Groups(['presence:read'])]
  public string $expiresAt = '';
}
