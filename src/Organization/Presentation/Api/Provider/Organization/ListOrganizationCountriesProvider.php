<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationCountryOptionOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function sprintf;
use function strtolower;

/**
 * Provider ListOrganizationCountriesProvider.
 *
 * Returns the list of supported countries for organization legal profile
 * country selects, including a flag image URL for each.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @implements ProviderInterface<OrganizationCountryOptionOutput>
 */
final readonly class ListOrganizationCountriesProvider implements ProviderInterface
{
  // #region Constants
  private const string FLAG_URL_TEMPLATE = 'https://flagcdn.com/w40/%s.png';

  /**
   * @var list<array{code: string, name: string}>
   */
  private const array SUPPORTED_COUNTRIES = [
    ['code' => 'FR', 'name' => 'France'],
    ['code' => 'BE', 'name' => 'Belgium'],
    ['code' => 'DE', 'name' => 'Germany'],
    ['code' => 'US', 'name' => 'United States'],
  ];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationCountriesProvider class.
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
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return list<OrganizationCountryOptionOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $outputs = [];
    foreach (self::SUPPORTED_COUNTRIES as $country) {
      $output = new OrganizationCountryOptionOutput();
      $output->code = $country['code'];
      $output->name = $country['name'];
      $output->flagUrl = sprintf(self::FLAG_URL_TEMPLATE, strtolower($country['code']));
      $outputs[] = $output;
    }

    return $outputs;
  }
  // #endregion
}
