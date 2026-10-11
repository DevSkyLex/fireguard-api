<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationLogo;

use Shared\Application\Message\ResultMessage;

/**
 * Result GetOrganizationLogoResult.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationLogoResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ?string $contents the public WebP bytes, or null when absent
   */
  public function __construct(public ?string $contents)
  {
  }
  // #endregion
}
