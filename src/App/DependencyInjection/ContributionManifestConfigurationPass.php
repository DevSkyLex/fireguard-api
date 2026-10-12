<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use InvalidArgumentException;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_array;
use function is_object;
use function is_string;
use function property_exists;
use function sprintf;

/**
 * Class ContributionManifestConfigurationPass
 *
 * Validates pure owner manifests in the selected composition without constructing infrastructure services.
 *
 * @category Configuration
 */
final readonly class ContributionManifestConfigurationPass implements CompilerPassInterface
{
  // #region Constants
  /**
   * Constant MANIFESTS
   *
   * Declares each public static manifest shape at the composition root.
   *
   * @var array<string, array{method:string, key:string, single?:bool}>
   */
  private const array MANIFESTS = [
    'authorization.permission_catalog_contribution' => ['method' => 'definitions', 'key' => 'name'],
    'authorization.role_catalog_contribution' => ['method' => 'definitions', 'key' => 'name'],
    'organization.quota_contribution' => ['method' => 'definitions', 'key' => 'key'],
    'organization.system_role_contribution' => ['method' => 'definitions', 'key' => 'key'],
    'organization.settings_policy_contribution' => ['method' => 'definitions', 'key' => 'key'],
    'organization.permission_contribution' => ['method' => 'definitions', 'key' => 'name'],
    'onboarding.organization_step_contribution' => ['method' => 'definition', 'key' => 'key', 'single' => true],
    'http.idempotency_operation' => ['method' => 'definitions', 'key' => 'name'],
  ];
  // #endregion

  // #region Methods
  /**
   * Method process
   *
   * Rejects duplicate selected owner keys and unknown seeded permission references before kernel boot.
   *
   * @access public
   *
   * @param ContainerBuilder $container the selected application configuration
   *
   * @return void
   */
  public function process(ContainerBuilder $container): void
  {
    $catalogs = [];
    foreach (self::MANIFESTS as $tag => $shape) {
      $catalogs[$tag] = $this->catalog($container, $tag, $shape);
    }
    $this->validateRolePermissions($catalogs);
  }

  /**
   * Method catalog
   *
   * Composes one selected manifest while preserving duplicate ownership validation.
   *
   * @access private
   *
   * @param ContainerBuilder $container the selected application configuration
   * @param string $tag the owner contribution tag
   * @param array{method:string, key:string, single?:bool} $shape the public manifest shape
   *
   * @return array<string, object> selected definitions indexed by ownership key
   */
  private function catalog(ContainerBuilder $container, string $tag, array $shape): array
  {
    $catalog = [];
    foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
      foreach ($this->definitions($container, $serviceId, $shape) as $definition) {
        $key = $this->ownershipKey($definition, $shape['key'], (string) $container->getDefinition($serviceId)->getClass());
        if (isset($catalog[$key])) {
          throw new InvalidArgumentException(sprintf('Duplicate contributed key "%s" for "%s".', $key, $tag));
        }
        $catalog[$key] = $definition;
      }
    }

    return $catalog;
  }

  /**
   * Method definitions
   *
   * Invokes only public static manifests without constructing infrastructure services.
   *
   * @access private
   *
   * @param ContainerBuilder $container the selected composition
   * @param string $serviceId the registered owner service
   * @param array{method:string, key:string, single?:bool} $shape the published manifest shape
   *
   * @return array<array-key, mixed> raw contributed definitions
   */
  private function definitions(ContainerBuilder $container, string $serviceId, array $shape): array
  {
    $class = $container->getDefinition($serviceId)->getClass();
    if (!is_string($class)) {
      throw new InvalidArgumentException(sprintf('Manifest service "%s" requires an explicit class.', $serviceId));
    }
    $method = new ReflectionMethod($class, $shape['method']);
    if (!$method->isPublic() || !$method->isStatic()) {
      throw new InvalidArgumentException(sprintf('Manifest "%s::%s" must be public and static.', $class, $shape['method']));
    }
    $result = $method->invoke(null);
    $definitions = ($shape['single'] ?? false) ? [$result] : $result;
    if (!is_array($definitions)) {
      throw new InvalidArgumentException(sprintf('Manifest "%s" must publish a definition list.', $class));
    }

    return $definitions;
  }

  /**
   * Method ownershipKey
   *
   * Rejects invalid manifest representations before indexing owner definitions.
   *
   * @access private
   *
   * @param mixed $definition the raw contributed definition
   * @param string $property the published ownership property
   * @param string $class the declaring owner class
   *
   * @return string the valid nonempty ownership key
   *
   * @phpstan-assert object $definition
   */
  private function ownershipKey(mixed $definition, string $property, string $class): string
  {
    if (!is_object($definition) || !property_exists($definition, $property) || !is_string($key = $definition->{$property}) || '' === $key) {
      throw new InvalidArgumentException(sprintf('Manifest "%s" has an invalid ownership key.', $class));
    }

    return $key;
  }

  /**
   * Method validateRolePermissions
   *
   * Rejects seeded roles referencing permissions outside the selected composition.
   *
   * @access private
   *
   * @param array<string, array<string, object>> $catalogs the selected owner catalogs
   *
   * @return void
   */
  private function validateRolePermissions(array $catalogs): void
  {
    foreach ($catalogs['authorization.role_catalog_contribution'] as $role) {
      if (!property_exists($role, 'permissionNames') || !is_array($role->permissionNames)) {
        throw new InvalidArgumentException('Role manifest requires explicit permission names.');
      }
      foreach ($role->permissionNames as $permission) {
        if (!is_string($permission) || !isset($catalogs['authorization.permission_catalog_contribution'][$permission])) {
          throw new InvalidArgumentException('Role manifest references an unselected permission.');
        }
      }
    }
  }
  // #endregion
}
