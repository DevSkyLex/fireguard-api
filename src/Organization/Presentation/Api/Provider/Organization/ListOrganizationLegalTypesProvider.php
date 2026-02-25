<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Domain\Service\OrganizationLegalRequirementsCatalog;
use Organization\Domain\ValueObject\{OrganizationCountryCode, OrganizationLegalType};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationLegalTypeOptionOutput;
use Shared\Domain\Exception\InvalidValueException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

/**
 * Provider ListOrganizationLegalTypesProvider.
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
  public function __construct(
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return list<OrganizationLegalTypeOptionOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $request = $this->requestStack->getCurrentRequest();
    $rawCountryCode = $request?->query->get('countryCode');

    try {
      $countryCode = OrganizationCountryCode::fromNullable($rawCountryCode);
    } catch (InvalidValueException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    }

    $outputs = [];
    foreach (OrganizationLegalType::cases() as $legalType) {
      $requirements = OrganizationLegalRequirementsCatalog::resolve($countryCode, $legalType);

      $output = new OrganizationLegalTypeOptionOutput();
      $output->countryCode = (string) $countryCode;
      $output->value = $legalType->value;
      $output->label = $this->labelFor($legalType);
      $output->requirements->registrationNumber->required = $requirements['registrationNumber']['required'];
      $output->requirements->registrationNumber->label = $requirements['registrationNumber']['label'];
      $output->requirements->registrationNumber->pattern = $requirements['registrationNumber']['pattern'];
      $output->requirements->registrationNumber->example = $requirements['registrationNumber']['example'];
      $output->requirements->vatNumber->required = $requirements['vatNumber']['required'];
      $output->requirements->vatNumber->label = $requirements['vatNumber']['label'];
      $output->requirements->vatNumber->pattern = $requirements['vatNumber']['pattern'];
      $output->requirements->vatNumber->example = $requirements['vatNumber']['example'];
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
      OrganizationLegalType::COMPANY => 'Company',
      OrganizationLegalType::NON_PROFIT => 'Non profit',
      OrganizationLegalType::PUBLIC_SECTOR => 'Public sector',
      OrganizationLegalType::INDIVIDUAL => 'Individual',
      OrganizationLegalType::OTHER => 'Other',
    };
  }
  // #endregion
}
