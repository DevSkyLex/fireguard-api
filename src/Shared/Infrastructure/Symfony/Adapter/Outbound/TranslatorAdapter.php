<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Shared\Application\Port\Outbound\TranslationPort;
use Shared\Infrastructure\Exception\TranslationException;
use Stringable;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Adapter TranslatorAdapter.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TranslatorAdapter implements TranslationPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the translator adapter.
   *
   * @since 1.0.0
   *
   * @param TranslatorInterface $translator the Symfony translator implementation
   */
  public function __construct(
    private readonly TranslatorInterface $translator,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method translate
   * {@inheritDoc}
   *
   * Translate a message using Symfony's translator.
   *
   * @since 1.0.0
   *
   * @param string $id the message identifier
   * @param array<string, scalar|Stringable> $parameters the parameters to replace in the message
   * @param ?string $domain the translation domain
   * @param ?string $locale the locale to use for translation
   *
   * @throws TranslationException if the translation fails
   *
   * @return string the translated message
   */
  public function translate(
    string $id,
    array $parameters = [],
    ?string $domain = null,
    ?string $locale = null,
  ): string {
    try {
      return $this->translator->trans(
        id: $id,
        parameters: $parameters,
        domain: $domain,
        locale: $locale,
      );
    } catch (Throwable $exception) {
      throw TranslationException::translateFailed(
        id: $id,
        previous: $exception,
      );
    }
  }
  // #endregion
}
