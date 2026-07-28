<?php

declare(strict_types=1);

namespace Organization\Domain\Catalog;

/**
 * Catalog PlanPresentationCatalog.
 *
 * Single source of truth for the MARKETING copy (tagline + perks bullet list)
 * shown on a plan's comparison card, keyed by the plan's stable machine key.
 * Mirrors the placement/style of `OrganizationApprovalDefaults`, but holds
 * translation message identifiers rather than the copy itself: the actual
 * strings live in `translations/plans.*.yaml` (domain
 * {@see self::TRANSLATION_DOMAIN}) so copy can be edited without touching
 * PHP, following the same translator seam already used for transactional
 * emails.
 *
 * IMPORTANT — marketing copy is NOT entitlement: this catalog only feeds
 * `PlanOutput::$tagline`/`PlanOutput::$perks` for display. It must never be
 * read to decide what a plan may do — that is exclusively `Plan::limitFor()`
 * / `OrganizationQuotaCatalog` (quota enforcement) and, for the Compliance
 * safety-register export, the `ComplianceExportEntitlementPort` allow-list. A
 * plan key present here with no matching business rule elsewhere (or vice
 * versa) is expected and must stay that way.
 *
 * @category Catalog
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanPresentationCatalog
{
  // #region Constants
  /**
   * Translation domain the tagline/perk message identifiers resolve against.
   */
  public const string TRANSLATION_DOMAIN = 'plans';

  /**
   * Tagline translation identifier per plan key. A plan key absent from this
   * map (e.g. a custom plan created by a platform administrator) has no
   * tagline.
   *
   * @var array<string, string>
   */
  private const array TAGLINE_IDS = [
    'free' => 'plan.free.tagline',
    'pro' => 'plan.pro.tagline',
    'max' => 'plan.max.tagline',
  ];

  /**
   * Perk translation identifiers per plan key, in display order. A plan key
   * absent from this map has no perks.
   *
   * @var array<string, list<string>>
   */
  private const array PERK_IDS = [
    'free' => [
      'plan.free.perks.0',
      'plan.free.perks.1',
      'plan.free.perks.2',
    ],
    'pro' => [
      'plan.pro.perks.0',
      'plan.pro.perks.1',
      'plan.pro.perks.2',
    ],
    'max' => [
      'plan.max.perks.0',
      'plan.max.perks.1',
      'plan.max.perks.2',
      'plan.max.perks.3',
    ],
  ];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Private to prevent instantiation: this catalog only exposes constants
   * and static lookups.
   *
   * @since 1.0.0
   *
   * @codeCoverageIgnore Never executed: the constructor exists only to
   * forbid instantiation of this static catalogue.
   */
  private function __construct()
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method taglineIdFor.
   *
   * Returns the tagline translation identifier for a plan key.
   *
   * @since 1.0.0
   *
   * @param string $planKey the plan's stable machine key
   *
   * @return ?string the translation identifier, or null when the plan has no tagline
   */
  public static function taglineIdFor(string $planKey): ?string
  {
    return self::TAGLINE_IDS[$planKey] ?? null;
  }

  /**
   * Method perkIdsFor.
   *
   * Returns the perk translation identifiers for a plan key, in display
   * order.
   *
   * @since 1.0.0
   *
   * @param string $planKey the plan's stable machine key
   *
   * @return list<string> the translation identifiers, empty when the plan has no perks
   */
  public static function perkIdsFor(string $planKey): array
  {
    return self::PERK_IDS[$planKey] ?? [];
  }
  // #endregion
}
