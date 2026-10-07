<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Service;

use Auth\Application\Contract\User\AuthenticatedUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;
use function preg_match;

/**
 * Class EquipmentTypeCatalogAccess.
 *
 * Applies the same fail-closed scope gate to every catalog operation.
 *
 * @category Service
 */
final readonly class EquipmentTypeCatalogAccess
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param Security $security authenticated security context
   * @param OrganizationAuthorizationPort $authorization organization permission decisions
   *
   * @return void
   */
  public function __construct(
    private Security $security,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method organization.
   *
   * @access public
   *
   * @param array<string, mixed> $uriVariables route identifiers
   * @param string $permission required permission
   *
   * @return string authorized organization identifier
   */
  public function organization(array $uriVariables, string $permission): string
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $organizationId)) {
      throw new BadRequestHttpException('A valid organizationId is required.');
    }
    $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, $permission);
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }

    return $organizationId;
  }
  // #endregion
}
