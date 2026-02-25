<?php

declare(strict_types=1);

namespace Onboarding\Presentation\Api\Serialization;

/**
 * Onboarding serialization groups.
 *
 * @category Serialization
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OnboardingSerializationGroup
{
  // #region Constants
  /**
   * Constant READ.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string READ = 'onboarding:read';

  /**
   * Constant WRITE.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string WRITE = 'onboarding:write';
  // #endregion
}
