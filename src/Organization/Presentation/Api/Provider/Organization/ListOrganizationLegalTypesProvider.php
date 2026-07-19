<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Domain\ValueObject\OrganizationLegalType;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationLegalTypeOptionOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provider ListOrganizationLegalTypesProvider.
 *
 * Returns the list of supported organization legal entity types for the
 * "Legal profile" settings tab select.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationLegalTypeOptionOutput>
 */
final readonly class ListOrganizationLegalTypesProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationLegalTypesProvider class.
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
   * Returns a list of organization legal type options for API consumers.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation context
   * @param array<string, mixed> $uriVariables the URI variables from the request
   * @param array<string, mixed> $context additional context for the provider
   *
   * @throws AccessDeniedHttpException if the user is not authenticated
   *
   * @return list<OrganizationLegalTypeOptionOutput> a list of organization legal type options
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $outputs = [];
    foreach (OrganizationLegalType::cases() as $legalType) {
      $output = new OrganizationLegalTypeOptionOutput();
      $output->value = $legalType->value;
      $output->label = $this->labelFor($legalType);
      $outputs[] = $output;
    }

    return $outputs;
  }

  /**
   * Method labelFor.
   *
   * @since 1.0.0
   */
  private function labelFor(OrganizationLegalType $legalType): string
  {
    return match ($legalType) {
      OrganizationLegalType::SOLE_PROPRIETORSHIP => 'Sole proprietorship',
      OrganizationLegalType::PARTNERSHIP => 'Partnership',
      OrganizationLegalType::LIMITED_LIABILITY_COMPANY => 'Limited liability company',
      OrganizationLegalType::PUBLIC_LIMITED_COMPANY => 'Public limited company',
      OrganizationLegalType::NON_PROFIT_ASSOCIATION => 'Non-profit association',
      OrganizationLegalType::PUBLIC_ENTITY => 'Public entity',
      OrganizationLegalType::OTHER => 'Other',
    };
  }
  // #endregion
}
