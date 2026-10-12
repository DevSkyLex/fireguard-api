<?php

declare(strict_types=1);

namespace User\Infrastructure\Adapter\Policy;

use SensitiveParameter;
use User\Application\Contract\Password\PasswordPolicyDescription;
use User\Application\Port\Outbound\PasswordPolicyPort;
use User\Domain\ValueObject\PasswordPolicy;

/**
 * Adapter ConfiguredPasswordPolicyAdapter.
 *
 * Maps server configuration to the domain's password invariant.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfiguredPasswordPolicyAdapter implements PasswordPolicyPort
{
  // #region Properties
  /**
   * @var PasswordPolicy authoritative rules
   */
  private PasswordPolicy $policy;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $minimumLength minimum character count
   * @param int $maximumLength maximum character count
   * @param bool $requireUppercase uppercase requirement
   * @param bool $requireLowercase lowercase requirement
   * @param bool $requireDigit digit requirement
   * @param string $specialCharacters special-character alphabet
   */
  public function __construct(
    int $minimumLength = 8,
    int $maximumLength = 128,
    bool $requireUppercase = true,
    bool $requireLowercase = true,
    bool $requireDigit = true,
    string $specialCharacters = '@$!%*?&#',
  ) {
    $this->policy = new PasswordPolicy($minimumLength, $maximumLength, $requireUppercase, $requireLowercase, $requireDigit, $specialCharacters);
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritDoc}
   */
  public function describe(): PasswordPolicyDescription
  {
    return new PasswordPolicyDescription($this->policy->minimumLength, $this->policy->maximumLength, $this->policy->requireUppercase, $this->policy->requireLowercase, $this->policy->requireDigit, $this->policy->specialCharacters, $this->policy->allowedInitialCharacters());
  }

  /**
   * {@inheritDoc}
   */
  public function violations(#[SensitiveParameter] string $password): array
  {
    return $this->policy->violations($password);
  }

  /**
   * {@inheritDoc}
   */
  public function assertAcceptable(#[SensitiveParameter] string $password): void
  {
    $this->policy->assertAcceptable($password);
  }
  // #endregion
}
