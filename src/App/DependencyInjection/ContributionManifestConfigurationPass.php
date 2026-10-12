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
      $catalog = [];
      foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
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
        foreach ($definitions as $definition) {
          if (!is_object($definition) || !property_exists($definition, $shape['key']) || !is_string($key = $definition->{$shape['key']}) || '' === $key) {
            throw new InvalidArgumentException(sprintf('Manifest "%s" has an invalid ownership key.', $class));
          }
          if (isset($catalog[$key])) {
            throw new InvalidArgumentException(sprintf('Duplicate contributed key "%s" for "%s".', $key, $tag));
          }
          $catalog[$key] = $definition;
        }
      }
      $catalogs[$tag] = $catalog;
    }
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
