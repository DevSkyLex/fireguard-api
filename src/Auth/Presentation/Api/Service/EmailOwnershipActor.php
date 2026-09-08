<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Service;

use Auth\Infrastructure\Security\User\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Service EmailOwnershipActor.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EmailOwnershipActor
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param Security $security current authenticated principal
   */
  public function __construct(private Security $security)
  {
  }
  // #endregion

  // #region Methods
  /**
   * @since 1.0.0
   *
   * @return string authenticated account identifier, never taken from request input
   */
  public function id(): string
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user->getId();
  }
  // #endregion
}
