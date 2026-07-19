<?php

declare(strict_types=1);

namespace Compliance\Domain\ValueObject;

use function array_column;

/**
 * Enum ComplianceStatus.
 *
 * The regulatory compliance verdict for a facility or an organization
 * rollup: `non_compliant` when at least one hard breach exists (overdue
 * equipment or an open critical non-conformity), `at_risk` when a softer
 * warning signal exists (equipment due soon or an open high-severity
 * non-conformity) with no hard breach, `compliant` when tracked equipment
 * exists and neither of the above applies, and `not_applicable` when the
 * facility has no schedule-tracked equipment and no open high/critical
 * non-conformity to grade against.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum ComplianceStatus: string
{
  case COMPLIANT = 'compliant';
  case AT_RISK = 'at_risk';
  case NON_COMPLIANT = 'non_compliant';
  case NOT_APPLICABLE = 'not_applicable';

  // #region Methods
  /**
   * Method values.
   *
   * Returns all supported compliance status values.
   *
   * @since 1.0.0
   *
   * @return list<string> the compliance status values
   */
  public static function values(): array
  {
    return array_column(self::cases(), 'value');
  }

  /**
   * Method severityRank.
   *
   * Ranks statuses from worst (highest) to best (lowest), used to derive an
   * organization rollup status from its facilities' statuses.
   * `NOT_APPLICABLE` ranks lowest (neutral): it never masks a real signal
   * from another facility, but an organization made only of `NOT_APPLICABLE`
   * facilities rolls up to `NOT_APPLICABLE` itself (handled by the caller).
   *
   * @since 1.0.0
   *
   * @return int the severity rank, higher is worse
   */
  public function severityRank(): int
  {
    return match ($this) {
      self::NON_COMPLIANT => 3,
      self::AT_RISK => 2,
      self::COMPLIANT => 1,
      self::NOT_APPLICABLE => 0,
    };
  }
  // #endregion
}
