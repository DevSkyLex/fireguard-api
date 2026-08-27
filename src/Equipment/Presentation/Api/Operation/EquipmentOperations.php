<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Operation;

/**
 * Equipment operation names.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentOperations
{
  // #region Constants
  /**
   * Constant CREATE_EQUIPMENT.
   *
   * @var string
   */
  public const string CREATE_EQUIPMENT = 'equipment_create';

  /**
   * Constant LIST_EQUIPMENTS.
   *
   * @var string
   */
  public const string LIST_EQUIPMENTS = 'equipment_list';

  /**
   * Constant LIST_FACILITY_EQUIPMENTS.
   *
   * @var string
   */
  public const string LIST_FACILITY_EQUIPMENTS = 'facility_equipment_list';

  /**
   * Constant GET_EQUIPMENT.
   *
   * @var string
   */
  public const string GET_EQUIPMENT = 'equipment_get';

  /**
   * Constant UPDATE_EQUIPMENT.
   *
   * @var string
   */
  public const string UPDATE_EQUIPMENT = 'equipment_update';

  /**
   * Constant ASSIGN_TO_FACILITY.
   *
   * @var string
   */
  public const string ASSIGN_TO_FACILITY = 'equipment_assign_to_facility';

  /**
   * Constant UNASSIGN_FROM_FACILITY.
   *
   * @var string
   */
  public const string UNASSIGN_FROM_FACILITY = 'equipment_unassign_from_facility';

  /**
   * Constant COMMISSION_EQUIPMENT.
   *
   * @var string
   */
  public const string COMMISSION_EQUIPMENT = 'equipment_commission';

  /**
   * Constant PUT_UNDER_MAINTENANCE.
   *
   * @var string
   */
  public const string PUT_UNDER_MAINTENANCE = 'equipment_maintenance';

  /**
   * Constant DECOMMISSION_EQUIPMENT.
   *
   * @var string
   */
  public const string DECOMMISSION_EQUIPMENT = 'equipment_decommission';

  /**
   * Constant ADD_TAG_TO_EQUIPMENT.
   *
   * @var string
   */
  public const string ADD_TAG_TO_EQUIPMENT = 'equipment_add_tag';

  /**
   * Constant REMOVE_TAG_FROM_EQUIPMENT.
   *
   * @var string
   */
  public const string REMOVE_TAG_FROM_EQUIPMENT = 'equipment_remove_tag';

  /**
   * Constant LIST_EQUIPMENT_ATTACHMENTS.
   *
   * @var string
   */
  public const string LIST_EQUIPMENT_ATTACHMENTS = 'equipment_attachments_list';

  /**
   * Constant ADD_ATTACHMENT.
   *
   * @var string
   */
  public const string ADD_ATTACHMENT = 'equipment_add_attachment';

  /**
   * Constant DELETE_ATTACHMENT.
   *
   * @var string
   */
  public const string DELETE_ATTACHMENT = 'equipment_delete_attachment';

  /**
   * Constant DOWNLOAD_ATTACHMENT.
   *
   * @var string
   */
  public const string DOWNLOAD_ATTACHMENT = 'equipment_download_attachment';

  /**
   * Constant LIST_TAGS.
   *
   * @var string
   */
  public const string LIST_TAGS = 'equipment_list_tags';

  /**
   * Constant LIST_MAINTENANCE_LOGS.
   *
   * @var string
   */
  public const string LIST_MAINTENANCE_LOGS = 'equipment_list_maintenance_logs';

  /**
   * Constant GET_EQUIPMENT_KPIS.
   *
   * @var string
   */
  public const string GET_EQUIPMENT_KPIS = 'equipment_get_kpis';

  /**
   * Constant SET_EQUIPMENT_PLAN_POSITION.
   *
   * @var string
   */
  public const string SET_EQUIPMENT_PLAN_POSITION = 'equipment_plan_position_set';
  // #endregion
}
