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

/**
 * Provider ListOrganizationInvitationStatusesProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationInvitationStatusOptionOutput>
 */
final readonly class ListOrganizationInvitationStatusesProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationInvitationStatusesProvider class.
   *
   * @since 1.0.0
   *
   * @param Security $security the security service
   */
  public function __construct(
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Provides organization invitation statuses for the requested API operation.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
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
  // #endregion
}
