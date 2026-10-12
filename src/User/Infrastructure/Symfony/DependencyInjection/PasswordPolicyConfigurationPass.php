<?php

declare(strict_types=1);

namespace User\Infrastructure\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TypeError;
use User\Infrastructure\Adapter\Policy\ConfiguredPasswordPolicyAdapter;

use function is_bool;
use function is_int;
use function is_string;

/**
 * Class PasswordPolicyConfigurationPass
 *
 * Validates the selected User module's safe policy parameters before lazy services are compiled.
 *
 * @category Configuration
 */
final readonly class PasswordPolicyConfigurationPass implements CompilerPassInterface
{
  // #region Methods
  /**
   * Method process
   *
   * Uses the same owner adapter and domain invariant as runtime password validation.
   * No credentials, external services or environment secrets are accessed.
   *
   * @access public
   *
   * @param ContainerBuilder $container the selected application composition
   *
   * @return void
   *
   * @throws TypeError when a policy parameter has the wrong YAML type
   */
  public function process(ContainerBuilder $container): void
  {
    if (!$container->hasDefinition(ConfiguredPasswordPolicyAdapter::class)) {
      return;
    }

    $minimum = $container->getParameter('password_policy.minimum_length');
    $maximum = $container->getParameter('password_policy.maximum_length');
    $uppercase = $container->getParameter('password_policy.require_uppercase');
    $lowercase = $container->getParameter('password_policy.require_lowercase');
    $digit = $container->getParameter('password_policy.require_digit');
    $special = $container->getParameter('password_policy.special_characters');
    if (!is_int($minimum) || !is_int($maximum) || !is_bool($uppercase) || !is_bool($lowercase) || !is_bool($digit) || !is_string($special)) {
      throw new TypeError('Password policy configuration requires integer bounds, boolean requirements and a string alphabet.');
    }

    new ConfiguredPasswordPolicyAdapter($minimum, $maximum, $uppercase, $lowercase, $digit, $special);
  }
  // #endregion
}
