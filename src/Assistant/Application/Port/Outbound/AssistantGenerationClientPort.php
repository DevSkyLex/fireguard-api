<?php

declare(strict_types=1);

namespace Assistant\Application\Port\Outbound;

use Assistant\Application\Contract\Generation\AssistantGenerationOutcome;

/**
 * Port AssistantGenerationClientPort.
 *
 * Calls the Ollama inference backend with streaming enabled. Implementations
 * must never throw on a reachability/timeout/HTTP failure — every outcome,
 * successful or not, is reported through the returned
 * {@see AssistantGenerationOutcome} so the caller can drive
 * {@see \Assistant\Domain\Model\Message\AssistantMessage}'s status machine
 * without a try/catch around a third-party transport exception.
 *
 * `$onFragment` is invoked with the FULL accumulated reply body so far on
 * every incremental content fragment received — never just the delta. This
 * is deliberate: it is what lets a Messenger retry on the `assistant`
 * transport (`max_retries: 1`) safely re-stream from scratch without ever
 * producing duplicated visible content, because the client only ever
 * REPLACES its displayed text with the latest published snapshot (mirrors
 * {@see \Assistant\Domain\Model\Message\AssistantMessage::markComplete()}'s
 * same "replace, not append" guarantee at the persistence layer).
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AssistantGenerationClientPort
{
  // #region Methods
  /**
   * Method streamChat.
   *
   * @since 1.0.0
   *
   * @param string $model the model identifier to use
   * @param list<array{role: string, content: string}> $messages the prompt messages, oldest first
   * @param float $temperature the sampling temperature
   * @param int $timeoutSeconds the outbound request timeout, in seconds
   * @param callable(string): void $onFragment invoked with the full accumulated reply body on each fragment
   *
   * @return AssistantGenerationOutcome the generation outcome
   */
  public function streamChat(string $model, array $messages, float $temperature, int $timeoutSeconds, callable $onFragment): AssistantGenerationOutcome;
  // #endregion
}
