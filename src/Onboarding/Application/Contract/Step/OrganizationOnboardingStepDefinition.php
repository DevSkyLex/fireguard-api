<?php

declare(strict_types=1);

namespace Onboarding\Application\Contract\Step;

use InvalidArgumentException;

use function preg_match;

/**
 * Class OrganizationOnboardingStepDefinition
 *
 * Publishes owner-controlled step metadata without framework or persistence types.
 *
 * @category Contract
 */
final readonly class OrganizationOnboardingStepDefinition
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $key stable persisted step key
   * @param string $label client-facing label
   * @param int $order canonical execution order
   * @param bool $required whether skipping is denied
   * @param string|null $actionMethod advertised action method
   * @param string|null $actionPath advertised path with optional organizationId placeholder
   * @param bool $rollbackable whether the organization creation supports its guarded archive rollback
   *
   * @return void
   */
  public function __construct(
    public string $key,
    public string $label,
    public int $order,
    public bool $required,
    public ?string $actionMethod = null,
    public ?string $actionPath = null,
    public bool $rollbackable = false,
  ) {
    if (1 !== preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $key)) {
      throw new InvalidArgumentException('Invalid onboarding step key.');
    }
  }
  // #endregion
}
