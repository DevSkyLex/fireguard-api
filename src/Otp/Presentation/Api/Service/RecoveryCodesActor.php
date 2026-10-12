<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Service;

use Auth\Application\Contract\User\AuthenticatedUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Class RecoveryCodesActor
 *
 * Resolves the immutable authenticated owner; browser input cannot select another enrollment.
 *
 * @category Service
 */
final readonly class RecoveryCodesActor
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param Security $security authenticated request context
   *
   * @return void
   */
  public function __construct(private Security $security)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method id
   *
   * @access public
   *
   * @return string authenticated user UUID
   */
  public function id(): string
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser || '' === $user->getId()) {
      throw new UnauthorizedHttpException('Bearer', 'User must be authenticated.');
    }

    return $user->getId();
  }
  // #endregion
}
