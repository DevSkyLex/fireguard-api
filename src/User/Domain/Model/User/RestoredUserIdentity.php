<?php

declare(strict_types=1);

namespace User\Domain\Model\User;

use Shared\Domain\ValueObject\{Email, TenantId};
use User\Domain\ValueObject\{UserId, Username};

/**
 * Identity values restored from a previously persisted user.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoredUserIdentity
{
  public function __construct(
    public UserId $id,
    public Username $username,
    public Email $email,
    public ?TenantId $tenantId,
  ) {
  }
}
