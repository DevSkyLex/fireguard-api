<?php

declare(strict_types=1);

namespace Assistant\Application\Contract\Generation;

/**
 * Contract AssistantGenerationOutcome.
 *
 * The outcome of a single streamed generation attempt through
 * {@see \Assistant\Application\Port\Outbound\AssistantGenerationClientPort}
 * (mirrors {@see \Webhook\Application\Contract\Http\WebhookHttpResponse}).
 * `errorCode` is null only when generation completed successfully; `body` is
 * only meaningful then.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantGenerationOutcome
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $body the complete generated reply body, once successful
   * @param ?int $tokenCount the generated token count, when reported
   * @param ?string $errorCode a stable failure code, when generation did not succeed
   * @param ?string $errorMessage a human-readable failure detail, when generation did not succeed
   */
  public function __construct(
    public string $body,
    public ?int $tokenCount = null,
    public ?string $errorCode = null,
    public ?string $errorMessage = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method isSuccessful.
   *
   * @since 1.0.0
   *
   * @return bool true when generation completed without error
   */
  public function isSuccessful(): bool
  {
    return null === $this->errorCode;
  }
  // #endregion
}
