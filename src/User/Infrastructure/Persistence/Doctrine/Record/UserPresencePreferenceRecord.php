<?php

declare(strict_types=1);

namespace User\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/**
 * Auth-owned account preference, independent from ephemeral organization presence.
 *
 * @category Record
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_presence_preferences')]
class UserPresencePreferenceRecord
{
  #[ORM\Id]
  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  public string $userId;

  #[ORM\Column(name: 'do_not_disturb', type: 'boolean', options: ['default' => false])]
  public bool $doNotDisturb = false;

  #[ORM\Column(type: 'boolean', options: ['default' => false])]
  public bool $invisible = false;

  #[ORM\Column(type: 'integer', options: ['default' => 0])]
  public int $revision = 0;
}
