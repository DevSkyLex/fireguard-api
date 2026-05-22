<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\OpenApi;

/**
 * Class OrganizationDashboardOpenApiValues.
 *
 * @category OpenApi
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationDashboardOpenApiValues
{
  // #region Constants
  /**
   * Constant GRANULARITIES.
   *
   * Defines valid values for time granularity
   * options in the organization dashboard.
   *
   * Values include:
   * - 'day': Daily granularity
   * - 'week': Weekly granularity
   * - 'month': Monthly granularity
   * - 'auto': Automatic granularity based on data range
   *
   * @since 1.0.0
   *
   * @var array<string>
   */
  public const array GRANULARITIES = [
    'day',
    'week',
    'month',
    'auto',
  ];

  /**
   * Constant FACILITY_TYPES.
   *
   * Defines valid values for facility types in the organization dashboard.
   *
   * Values include:
   * - 'site': Represents a site facility type
   * - 'building': Represents a building facility type
   * - 'floor': Represents a floor facility type
   * - 'zone': Represents a zone facility type
   * - 'area': Represents an area facility type
   *
   * @since 1.0.0
   *
   * @var array<string>
   */
  public const array FACILITY_TYPES = [
    'site',
    'building',
    'floor',
    'zone',
    'area',
  ];

  /**
   * Constant EQUIPMENT_TYPES.
   *
   * Defines valid values for equipment types in the organization dashboard.
   *
   * Values include:
   * - 'fire_extinguisher': Represents a fire extinguisher
   * - 'smoke_detector': Represents a smoke detector
   * - 'heat_detector': Represents a heat detector
   * - 'sprinkler': Represents a sprinkler
   * - 'fire_alarm_panel': Represents a fire alarm panel
   *
   * @since 1.0.0
   *
   * @var array<string>
   */
  public const array EQUIPMENT_TYPES = [
    'fire_extinguisher',
    'smoke_detector',
    'heat_detector',
    'sprinkler',
    'fire_alarm_panel',
    'hydrant',
    'fire_door',
    'emergency_lighting',
    'access_control',
    'camera',
    'gas_detector',
    'other',
  ];

  /**
   * Constant EQUIPMENT_STATUSES.
   *
   * Defines valid values for equipment statuses in the organization dashboard.
   *
   * Values include:
   * - 'in_stock': Equipment is in stock and not yet deployed
   * - 'operational': Equipment is deployed and operational
   * - 'under_maintenance': Equipment is currently under maintenance
   * - 'decommissioned': Equipment is decommissioned and no longer in use
   *
   * @since 1.0.0
   *
   * @var array<string>
   */
  public const array EQUIPMENT_STATUSES = [
    'in_stock',
    'operational',
    'under_maintenance',
    'decommissioned',
  ];

  /**
   * Constant INSPECTION_STATUSES.
   *
   * Defines valid values for inspection statuses in the organization dashboard.
   *
   * Values include:
   * - 'draft': Inspection is in draft state and not yet submitted
   * - 'submitted': Inspection has been submitted and is awaiting review
   * - 'closed': Inspection has been reviewed and closed
   *
   * @since 1.0.0
   *
   * @var array<string>
   */
  public const array INSPECTION_STATUSES = [
    'draft',
    'submitted',
    'closed',
  ];

  /**
   * Constant INSPECTION_RESULTS.
   *
   * Defines valid values for inspection results in the organization dashboard.
   *
   * Values include:
   * - 'pass': Inspection passed
   * - 'fail': Inspection failed
   * - 'partial': Inspection partially passed
   *
   * @since 1.0.0
   *
   * @var array<string>
   */
  public const array INSPECTION_RESULTS = [
    'pass',
    'fail',
    'partial',
  ];

  /**
   * Constant INSPECTOR_TYPES.
   *
   * Defines valid values for inspector types in the organization dashboard.
   *
   * Values include:
   * - 'user': Represents a user inspector
   * - 'external': Represents an external inspector
   *
   * @since 1.0.0
   *
   * @var array<string>
   */
  public const array INSPECTOR_TYPES = [
    'user',
    'external',
  ];

  /**
   * Constant NON_CONFORMITY_STATUSES.
   *
   * Defines valid values for non-conformity statuses in the organization dashboard.
   *
   * Values include:
   * - 'open': Non-conformity is open
   * - 'in_progress': Non-conformity is in progress
   * - 'done': Non-conformity is done
   * - 'waived': Non-conformity is waived
   *
   * @since 1.0.0
   *
   * @var array<string>
   */
  public const array NON_CONFORMITY_STATUSES = [
    'open',
    'in_progress',
    'done',
    'waived',
  ];

  /**
   * Constant NON_CONFORMITY_SEVERITIES.
   *
   * Defines valid values for non-conformity severities in the organization dashboard.
   *
   * Values include:
   * - 'low': Low severity
   * - 'medium': Medium severity
   * - 'high': High severity
   * - 'critical': Critical severity
   *
   * @since 1.0.0
   *
   * @var array<string>
   */
  public const array NON_CONFORMITY_SEVERITIES = [
    'low',
    'medium',
    'high',
    'critical',
  ];
  // #endregion Constants
}
