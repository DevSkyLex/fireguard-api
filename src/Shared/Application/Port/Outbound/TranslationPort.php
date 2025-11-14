<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Stringable;

/**
 * Port TranslationPort
 *
 * Port used to translate messages
 * within the application.
 *
 * @category Outbound Port
 * @package Shared\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TranslationPort
{
  //#region Methods
  /**
   * Method translate
   *
   * Translate the given message identifier.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $id The translation message identifier.
   * @param array<string, scalar|Stringable> $parameters The parameters for placeholders.
   * @param ?string $domain The translation domain to use.
   * @param ?string $locale The locale override to apply.
   *
   * @return string The translated message.
   */
  public function translate(
    string $id,
    array $parameters = [],
    ?string $domain = null,
    ?string $locale = null
  ): string;
  //#endregion
}
