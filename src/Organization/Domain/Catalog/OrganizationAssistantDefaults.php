<?php

declare(strict_types=1);

namespace Organization\Domain\Catalog;

/**
 * Catalog OrganizationAssistantDefaults.
 *
 * Single source of truth for the AI-assistant defaults every organization
 * starts from. The assistant is DISABLED by default (opt-in): a conversation
 * transcript is sent to the inference backend, so an organization must
 * deliberately turn it on rather than inherit it, mirroring
 * {@see OrganizationApprovalDefaults}.
 *
 * The model name is stored as a plain string and is NOT validated here: the
 * set of servable models is operator deployment configuration
 * (`OLLAMA_ALLOWED_MODELS`), not domain knowledge, and the Organization domain
 * must never depend on it. The API layer validates the submitted model against
 * that allowlist — the same split used for approval action types.
 *
 * @category Catalog
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationAssistantDefaults
{
  // #region Constants
  /**
   * Whether the assistant is available to the organization, by default.
   */
  public const bool ENABLED = false;

  /**
   * Default model override. `null` means "use the operator-configured default
   * model" (`OLLAMA_DEFAULT_MODEL`) rather than pinning one per organization.
   */
  public const ?string MODEL = null;

  /**
   * Default sampling temperature. Deliberately low: the assistant summarizes
   * and reports on regulated fire-safety work, where inventiveness is a defect.
   */
  public const float TEMPERATURE = 0.2;

  /**
   * Inclusive bounds for the sampling temperature.
   */
  public const float MIN_TEMPERATURE = 0.0;

  public const float MAX_TEMPERATURE = 2.0;

  /**
   * Whether the server pre-injects a bounded business-context block (compliance
   * summary, open non-conformities, upcoming due dates) alongside the thread
   * transcript. Organizations that want the assistant to see nothing beyond the
   * conversation itself can turn this off while keeping the assistant enabled.
   */
  public const bool INCLUDE_BUSINESS_CONTEXT = true;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Private to prevent instantiation: this catalog only exposes constants.
   *
   * @since 1.0.0
   */
  private function __construct()
  {
  }
  // #endregion
}
