<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Persistence\Doctrine\Record;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class UserRoleRecord
 *
 * Authorization owns role membership independently of the User persistence model.
 * The scalar identifiers preserve the existing join table and its composite key.
 *
 * @category Record
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_roles')]
#[ORM\Index(name: 'IDX_54FCD59FA76ED395', columns: ['user_id'])]
#[ORM\Index(name: 'IDX_54FCD59FD60322AC', columns: ['role_id'])]
class UserRoleRecord
{
  // #region Properties
  /**
   * Property userId
   *
   * Identifies the account without depending on its owning module's Record.
   */
  #[ORM\Id]
  #[ORM\Column(name: 'user_id', type: 'string', length: 36)]
  public string $userId;

  /**
   * Property roleId
   *
   * Identifies the assigned role; both identifiers form the membership identity.
   */
  #[ORM\Id]
  #[ORM\Column(name: 'role_id', type: 'string', length: 36)]
  public string $roleId;
  // #endregion
}
