<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Domain\ValueObject\FacilityType;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityTypeOptionOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provider ListFacilityTypesProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<FacilityTypeOptionOutput>
 */
final readonly class ListFacilityTypesProvider implements ProviderInterface
{
  // #region Constructor
  public function __construct(
    private Security $security,
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
   * @return list<FacilityTypeOptionOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $outputs = [];
    foreach (FacilityType::cases() as $facilityType) {
      $output = new FacilityTypeOptionOutput();
      $output->value = $facilityType->value;
      $output->label = $this->labelFor($facilityType);
      $outputs[] = $output;
    }

    return $outputs;
  }

  /**
   * Method labelFor.
   *
   * @since 1.0.0
   */
  private function labelFor(FacilityType $facilityType): string
  {
    return match ($facilityType) {
      FacilityType::SITE => 'Site',
      FacilityType::BUILDING => 'Building',
      FacilityType::FLOOR => 'Floor',
      FacilityType::ZONE => 'Zone',
      FacilityType::AREA => 'Area',
    };
  }
  // #endregion
}
