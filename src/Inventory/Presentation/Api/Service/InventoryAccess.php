<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Service;

use Auth\Application\Contract\User\AuthenticatedUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException,BadRequestHttpException,NotFoundHttpException};

use function is_string;
use function preg_match;

/** Organization scope and dedicated quantity/financial permissions. @category Service */
final readonly class InventoryAccess
{
  public function __construct(private Security $security, private OrganizationAuthorizationPort $authorization)
  {
  }

  public function actor(): string
  {
    $user = $this->security->getUser();
    if (!$user instanceof AuthenticatedUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user->getId();
  }

  /**
   * @param array<string,mixed> $uriVariables
   */
  public function organization(array $uriVariables, string $permission): string
  {
    $org = $uriVariables['organizationId'] ?? null;
    if (!is_string($org) || 1 !== preg_match('/^[0-9a-f-]{36}$/iD', $org)) {
      throw new BadRequestHttpException('A valid organizationId is required.');
    }
    $decision = $this->authorization->resolveAccess($this->actor(), $org, $permission);
    if ($decision->isOutsideScope()) {
      throw new NotFoundHttpException('Organization not found.');
    }
    if (!$decision->isGranted()) {
      throw new AccessDeniedHttpException('Missing ' . $permission . ' permission.');
    }

    return $org;
  }

  public function financial(string $org): bool
  {
    return $this->authorization->resolveAccess($this->actor(), $org, 'organization.maintenance_cost.read')->isGranted();
  }

  public function requirePermission(string $org, string $permission): void
  {
    $this->organization(['organizationId' => $org], $permission);
  }
}
