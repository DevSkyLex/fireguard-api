<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Domain\ValueObject\OrganizationInvitationStatus;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInvitationStatusOptionOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** @implements ProviderInterface<OrganizationInvitationStatusOptionOutput> */
final readonly class ListOrganizationInvitationStatusesProvider implements ProviderInterface
{
  public function __construct(
    private Security $security,
  ) {
  }

  /**
   * @return list<OrganizationInvitationStatusOptionOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $outputs = [];
    foreach (OrganizationInvitationStatus::cases() as $status) {
      $output = new OrganizationInvitationStatusOptionOutput();
      $output->value = $status->value;
      $output->label = $status->label();
      $outputs[] = $output;
    }

    return $outputs;
  }
}
