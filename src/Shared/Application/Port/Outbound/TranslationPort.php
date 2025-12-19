<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Stringable;

/**
 * Port TranslationPort.
 *
 * Port used to translate messages
 * within the application.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface TranslationPort
{
    // #region Methods
    /**
     * Method translate.
     *
     * Translate the given message identifier.
     *
     * @since 1.0.0
     *
     * @param string                           $id         the translation message identifier
     * @param array<string, scalar|Stringable> $parameters the parameters for placeholders
     * @param ?string                          $domain     the translation domain to use
     * @param ?string                          $locale     the locale override to apply
     *
     * @return string the translated message
     */
    public function translate(
        string $id,
        array $parameters = [],
        ?string $domain = null,
        ?string $locale = null,
    ): string;
    // #endregion
}
