<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

/**
 * Port OrganizationLogoImagePort.
 *
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationLogoImagePort
{
  // #region Methods
  /**
   * Validates and encodes the bounded canonical logo variant.
   *
   * @since 1.0.0
   *
   * @param string $sourceContents the uploaded source bytes
   *
   * @return string the encoded WebP bytes
   */
  public function encode(string $sourceContents): string;
  // #endregion
}
