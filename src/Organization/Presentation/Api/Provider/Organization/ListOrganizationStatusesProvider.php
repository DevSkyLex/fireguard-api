<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Domain\ValueObject\OrganizationStatus;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationStatusOptionOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provider ListOrganizationStatusesProvider.
 *
 * Returns the list of supported organization statuses
 * for organization status selects.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationStatusOptionOutput>
 */
final readonly class ListOrganizationStatusesProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationStatusesProvider class.
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
   * Returns a list of organization status options for API consumers.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation context
   * @param array<string, mixed> $uriVariables the URI variables from the request
   * @param array<string, mixed> $context additional context for the provider
   *
   * @throws AccessDeniedHttpException if the user is not authenticated
   *
   * @return list<OrganizationStatusOptionOutput> a list of organization status options
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $outputs = [];
    foreach (OrganizationStatus::cases() as $status) {
      $output = new OrganizationStatusOptionOutput();
      $output->value = $status->value;
      $output->label = $status->label();
      $outputs[] = $output;
    }

    return $outputs;
  }
  // #endregion
}
