<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\GrantConsent;

use Shared\Application\Message\ResultMessage;

/**
 * Result GrantConsentResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GrantConsentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $consentId the consent ID
   * @param bool   $isNew     whether this is a new consent
   */
  public function __construct(
    public string $consentId,
    public bool $isNew,
  ) {
  }
  // #endregion
}
