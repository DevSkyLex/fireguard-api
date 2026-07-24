<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO PingPresenceInput.
 *
 * `POST /api/presence/ping` request body (L2.7). There is deliberately no
 * `memberId` field — the acting member is ALWAYS resolved server-side from
 * the authenticated caller, so a member can never ping presence as someone
 * else.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PingPresenceInput
{
  /**
   * Property organization.
   *
   * The organization IRI (or bare identifier).
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank(message: 'Organization is required.')]
  public string $organization = '';
}
