<?php

declare(strict_types=1);

namespace Inspection\Presentation\Api\Service;

use Auth\Application\Contract\User\AuthenticatedUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function is_string;

/**
 * Enforces both inspection and equipment read entitlement before parc projection.
 *
 * @category Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ParkAnomaliesAccess
{
  /**
   * @since 1.0.0
   */
  public function __construct(private Security $security, private OrganizationAuthorizationPort $authorization)
  {
  }

  /**
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables owner scope
   */
  public function organization(array $uriVariables): string
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId is required.');
    }
    foreach (['organization.inspection.read', 'organization.equipment.read'] as $permission) {
      $decision = $this->authorization->resolveAccess($user->getId(), $organizationId, $permission);
      if ($decision->isOutsideScope()) {
        throw new NotFoundHttpException('Organization not found.');
      }
      if (!$decision->isGranted()) {
        throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
      }
    }

    return $organizationId;
  }
}
