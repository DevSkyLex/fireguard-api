<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Provider\Equipment;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Domain\ValueObject\EquipmentStatus;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentStatusOptionOutput;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/** @implements ProviderInterface<EquipmentStatusOptionOutput> */
final readonly class ListEquipmentStatusesProvider implements ProviderInterface
{
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }

  /**
   * @return list<EquipmentStatusOptionOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.equipment.read')) {
      throw new AccessDeniedHttpException('Missing organization.equipment.read permission.');
    }

    $outputs = [];
    foreach (EquipmentStatus::cases() as $status) {
      $output = new EquipmentStatusOptionOutput();
      $output->value = $status->value;
      $output->label = $status->label();
      $outputs[] = $output;
    }

    return $outputs;
  }
}
