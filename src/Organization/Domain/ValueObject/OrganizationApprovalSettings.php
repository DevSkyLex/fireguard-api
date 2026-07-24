<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Organization\Domain\Catalog\{OrganizationApprovalDefaults, OrganizationSystemRoleCatalog};
use Shared\Domain\Exception\InvalidValueException;

use function array_key_exists;
use function in_array;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function sprintf;

/**
 * ValueObject OrganizationApprovalSettings.
 *
 * Organization-wide four-eyes approval policy: which regulated action types
 * require a second authorized member's decision, the minimum approver role
 * per action type, whether the requester may also decide on their own
 * request, and how long a pending request stays valid. Only the action
 * types the organization customized are stored; every action type defaults
 * to DISABLED (opt-in) — mirrors {@see OrganizationComplianceSettings}.
 *
 * Action-type keys are stored as plain strings and are not validated here
 * (the Organization domain never depends on the Approval module); the API
 * layer validates them against the Approval action-type catalog port.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationApprovalSettings
{
  // #region Properties
  /**
   * Customized per-action-type rules (subset of action types).
   *
   * @since 1.0.0
   *
   * @var array<string, array{enabled: bool, minApproverRole: string, minSeverity: ?string}>
   */
  public array $actionRules;

  /**
   * Whether the requester may also decide on their own request.
   *
   * @since 1.0.0
   */
  public bool $allowSelfApproval;

  /**
   * Number of days a pending request stays valid before it expires.
   *
   * @since 1.0.0
   */
  public int $approvalTtlDays;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param array<string, array{enabled: bool, minApproverRole: string, minSeverity: ?string}> $actionRules the customized per-action-type rules (validated)
   * @param bool $allowSelfApproval whether the requester may also decide on their own request
   * @param int $approvalTtlDays the number of days a pending request stays valid (validated)
   */
  public function __construct(
    array $actionRules = [],
    bool $allowSelfApproval = OrganizationApprovalDefaults::ALLOW_SELF_APPROVAL,
    int $approvalTtlDays = OrganizationApprovalDefaults::APPROVAL_TTL_DAYS,
  ) {
    $this->actionRules = self::validateActionRules($actionRules);
    $this->allowSelfApproval = $allowSelfApproval;
    $this->approvalTtlDays = self::validateTtl($approvalTtlDays);
  }
  // #endregion

  // #region Methods
  /**
   * Method ruleFor.
   *
   * Returns the effective rule for an action type: the customization when
   * present, otherwise the "disabled" default.
   *
   * @since 1.0.0
   *
   * @param string $actionType the action type value
   *
   * @return array{enabled: bool, minApproverRole: string, minSeverity: ?string} the effective rule
   */
  public function ruleFor(string $actionType): array
  {
    return $this->actionRules[$actionType] ?? [
      'enabled' => false,
      'minApproverRole' => OrganizationApprovalDefaults::MIN_APPROVER_ROLE,
      'minSeverity' => null,
    ];
  }

  /**
   * Method mergedWith.
   *
   * Applies a partial section payload (snake_case keys) on top of the
   * current settings and returns the resulting instance. Inside
   * `action_rules`, a `null` entry removes the customization (reverting the
   * action type to "disabled"); a non-null entry partially overlays the
   * given fields onto the current (or default) rule for that action type.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $partial the partial section payload
   *
   * @return self the merged instance
   */
  public function mergedWith(array $partial): self
  {
    $actionRules = $this->actionRules;

    if (array_key_exists('action_rules', $partial) && is_array($partial['action_rules'])) {
      foreach ($partial['action_rules'] as $actionType => $rule) {
        if (!is_string($actionType)) {
          continue;
        }

        if (null === $rule) {
          unset($actionRules[$actionType]);

          continue;
        }

        if (!is_array($rule)) {
          continue;
        }

        $current = $actionRules[$actionType] ?? [
          'enabled' => false,
          'minApproverRole' => OrganizationApprovalDefaults::MIN_APPROVER_ROLE,
          'minSeverity' => null,
        ];

        $actionRules[$actionType] = [
          'enabled' => isset($rule['enabled']) && is_bool($rule['enabled']) ? $rule['enabled'] : $current['enabled'],
          'minApproverRole' => isset($rule['min_approver_role']) && is_string($rule['min_approver_role']) && '' !== $rule['min_approver_role']
            ? $rule['min_approver_role']
            : $current['minApproverRole'],
          'minSeverity' => array_key_exists('min_severity', $rule)
            ? (is_string($rule['min_severity']) && '' !== $rule['min_severity'] ? $rule['min_severity'] : null)
            : $current['minSeverity'],
        ];
      }
    }

    $allowSelfApproval = $this->allowSelfApproval;
    if (isset($partial['allow_self_approval']) && is_bool($partial['allow_self_approval'])) {
      $allowSelfApproval = $partial['allow_self_approval'];
    }

    $approvalTtlDays = $this->approvalTtlDays;
    if (isset($partial['approval_ttl_days']) && is_int($partial['approval_ttl_days'])) {
      $approvalTtlDays = $partial['approval_ttl_days'];
    }

    /** @var array<string, array{enabled: bool, minApproverRole: string, minSeverity: ?string}> $actionRules */
    return new self(
      actionRules: $actionRules,
      allowSelfApproval: $allowSelfApproval,
      approvalTtlDays: $approvalTtlDays,
    );
  }

  /**
   * Method toArray.
   *
   * Returns the customized approval settings as a serializable array. Only
   * customizations are persisted; effective values are recomputed on read.
   *
   * @since 1.0.0
   *
   * @return array{action_rules: array<string, array{enabled: bool, min_approver_role: string, min_severity: ?string}>, allow_self_approval: bool, approval_ttl_days: int} the settings array
   */
  public function toArray(): array
  {
    $actionRules = [];
    foreach ($this->actionRules as $actionType => $rule) {
      $actionRules[$actionType] = [
        'enabled' => $rule['enabled'],
        'min_approver_role' => $rule['minApproverRole'],
        'min_severity' => $rule['minSeverity'],
      ];
    }

    return [
      'action_rules' => $actionRules,
      'allow_self_approval' => $this->allowSelfApproval,
      'approval_ttl_days' => $this->approvalTtlDays,
    ];
  }

  /**
   * Method fromArray.
   *
   * @static
   *
   * Creates approval settings from a (possibly partial) array, falling back
   * to "nothing customized" (every action type disabled) for any missing
   * key.
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $data the settings data
   *
   * @return self the settings instance
   */
  public static function fromArray(array $data): self
  {
    $actionRulesRaw = $data['action_rules'] ?? [];
    $actionRules = [];

    if (is_array($actionRulesRaw)) {
      foreach ($actionRulesRaw as $actionType => $rule) {
        if (!is_string($actionType) || !is_array($rule)) {
          continue;
        }

        $minApproverRole = $rule['min_approver_role'] ?? null;
        $minSeverity = $rule['min_severity'] ?? null;

        $actionRules[$actionType] = [
          'enabled' => (bool) ($rule['enabled'] ?? false),
          'minApproverRole' => is_string($minApproverRole) && '' !== $minApproverRole ? $minApproverRole : OrganizationApprovalDefaults::MIN_APPROVER_ROLE,
          'minSeverity' => is_string($minSeverity) && '' !== $minSeverity ? $minSeverity : null,
        ];
      }
    }

    $approvalTtlDays = $data['approval_ttl_days'] ?? OrganizationApprovalDefaults::APPROVAL_TTL_DAYS;

    return new self(
      actionRules: $actionRules,
      allowSelfApproval: (bool) ($data['allow_self_approval'] ?? OrganizationApprovalDefaults::ALLOW_SELF_APPROVAL),
      approvalTtlDays: is_int($approvalTtlDays) ? $approvalTtlDays : OrganizationApprovalDefaults::APPROVAL_TTL_DAYS,
    );
  }

  /**
   * Method validateActionRules.
   *
   * @static
   *
   * Validates the customized action rules: known roles, known severities.
   *
   * @since 1.0.0
   *
   * @param array<string, array{enabled: bool, minApproverRole: string, minSeverity: ?string}> $actionRules the customized action rules
   *
   * @return array<string, array{enabled: bool, minApproverRole: string, minSeverity: ?string}> the validated action rules
   */
  private static function validateActionRules(array $actionRules): array
  {
    $allowedRoles = [OrganizationSystemRoleCatalog::MEMBER, OrganizationSystemRoleCatalog::ADMIN];

    foreach ($actionRules as $actionType => $rule) {
      if ('' === $actionType) {
        throw InvalidValueException::because('Approval action type keys must be non-empty strings.');
      }

      $minApproverRole = $rule['minApproverRole'];
      if (!in_array($minApproverRole, $allowedRoles, true)) {
        throw InvalidValueException::because(sprintf(
          'Unknown minimum approver role "%s" for approval action type "%s".',
          (string) $minApproverRole,
          $actionType,
        ));
      }

      $minSeverity = $rule['minSeverity'] ?? null;
      if (null !== $minSeverity && !in_array($minSeverity, OrganizationComplianceSettings::ALLOWED_SEVERITIES, true)) {
        throw InvalidValueException::because(sprintf(
          'Unknown minimum severity "%s" for approval action type "%s".',
          (string) $minSeverity,
          $actionType,
        ));
      }
    }

    return $actionRules;
  }

  /**
   * Method validateTtl.
   *
   * @static
   *
   * Validates the approval TTL bounds.
   *
   * @since 1.0.0
   *
   * @param int $approvalTtlDays the approval TTL, in days
   *
   * @return int the validated approval TTL
   */
  private static function validateTtl(int $approvalTtlDays): int
  {
    if ($approvalTtlDays < OrganizationApprovalDefaults::MIN_APPROVAL_TTL_DAYS || $approvalTtlDays > OrganizationApprovalDefaults::MAX_APPROVAL_TTL_DAYS) {
      throw InvalidValueException::because(sprintf(
        'Approval TTL must be between %d and %d days.',
        OrganizationApprovalDefaults::MIN_APPROVAL_TTL_DAYS,
        OrganizationApprovalDefaults::MAX_APPROVAL_TTL_DAYS,
      ));
    }

    return $approvalTtlDays;
  }
  // #endregion
}
