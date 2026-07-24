<?php

declare(strict_types=1);

namespace Assistant\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception AssistantValidationException.
 *
 * Domain-level validation guard, defense-in-depth behind the Presentation
 * layer's DTO constraints (mirrors
 * {@see \Webhook\Domain\Exception\WebhookValidationException}).
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantValidationException extends RuntimeException
{
  // #region Methods
  /**
   * Method blankBody.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function blankBody(): self
  {
    return new self('An assistant message body cannot be blank.');
  }

  /**
   * Method modelNotAllowed.
   *
   * @static
   *
   * Raised by {@see \Assistant\Domain\Service\AssistantModelPolicy} — the
   * authoritative gate behind
   * `Presentation\Api\Validator\ValidAssistantModel\ValidAssistantModelValidator`'s
   * UX-only input-time check — when a tenant-supplied model is not in the
   * operator-curated `OLLAMA_ALLOWED_MODELS` allowlist.
   *
   * @since 1.0.0
   *
   * @param string $model the rejected model identifier
   *
   * @return self the exception instance
   */
  public static function modelNotAllowed(string $model): self
  {
    return new self(sprintf('The model "%s" is not permitted by the configured allowlist.', $model));
  }
  // #endregion
}
