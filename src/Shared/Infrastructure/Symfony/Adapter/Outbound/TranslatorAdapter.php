<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Shared\Application\Port\Outbound\TranslationPort;
use Shared\Infrastructure\Symfony\Exception\TranslationException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Adapter TranslatorAdapter
 * @implements TranslationPort
 * @final
 *
 * Adapter bridging the translation outbound port with
 * Symfony's translator component.
 *
 * @category Outbound Adapter
 * @package Shared\Infrastructure\Symfony\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TranslatorAdapter implements TranslationPort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the translator adapter.
   *
   * @access public
   * @since 1.0.0
   *
   * @param TranslatorInterface $translator The Symfony translator implementation.
   */
  public function __construct(
    private readonly TranslatorInterface $translator
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method translate
   * @method translate(
   *  string $id,
   *  array $parameters = [],
   *  ?string $domain = null,
   *  ?string $locale = null
   * ): string
   * {@inheritDoc}
   *
   * Translate a message using Symfony's translator.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $id The message identifier.
   * @param array $parameters The parameters to replace in the message.
   * @param ?string $domain The translation domain.
   * @param ?string $locale The locale to use for translation.
   *
   * @return string The translated message.
   *
   * @throws TranslationException If the translation fails.
   */
  public function translate(
    string $id,
    array $parameters = [],
    ?string $domain = null,
    ?string $locale = null
  ): string {
    try {
      return $this->translator->trans(
        id: $id,
        parameters: $parameters,
        domain: $domain,
        locale: $locale
      );
    }
    catch (Throwable $exception) {
      throw TranslationException::translateFailed(
        id: $id,
        previous: $exception
      );
    }
  }
  //#endregion
}
