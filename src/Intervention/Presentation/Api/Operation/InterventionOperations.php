<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Operation;

/**
 * Intervention operation names.
 *
 * @category Operation
 *
 * @version 1.1.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionOperations
{
  // #region Constants
  // Intervention
  /**
   * Constant CREATE_INTERVENTION.
   *
   * @var string
   */
  public const string CREATE_INTERVENTION = 'intervention_create';

  /**
   * Constant PUT_INTERVENTION.
   *
   * @var string
   */
  public const string PUT_INTERVENTION = 'intervention_put';

  /**
   * Constant LIST_INTERVENTIONS.
   *
   * @var string
   */
  public const string LIST_INTERVENTIONS = 'intervention_list';

  /**
   * Constant GET_INTERVENTION.
   *
   * @var string
   */
  public const string GET_INTERVENTION = 'intervention_get';

  /**
   * Constant UPDATE_INTERVENTION.
   *
   * @var string
   */
  public const string UPDATE_INTERVENTION = 'intervention_update';

  /**
   * Constant DELETE_INTERVENTION.
   *
   * @var string
   */
  public const string DELETE_INTERVENTION = 'intervention_delete';

  // Intervention statistics
  /**
   * Constant GET_INTERVENTION_STATISTICS.
   *
   * @var string
   */
  public const string GET_INTERVENTION_STATISTICS = 'intervention_get_statistics';

  // Intervention team assignment
  /**
   * Constant ASSIGN_INTERVENTION_TEAM.
   *
   * @var string
   */
  public const string ASSIGN_INTERVENTION_TEAM = 'intervention_team_assign';

  // Intervention activity feed
  /**
   * Constant LIST_INTERVENTION_ACTIVITIES.
   *
   * @var string
   */
  public const string LIST_INTERVENTION_ACTIVITIES = 'intervention_activity_list';

  /**
   * Constant CREATE_INTERVENTION_COMMENT.
   *
   * @var string
   */
  public const string CREATE_INTERVENTION_COMMENT = 'intervention_comment_create';

  // Intervention attachments
  /**
   * Constant CREATE_INTERVENTION_ATTACHMENT.
   *
   * @var string
   */
  public const string CREATE_INTERVENTION_ATTACHMENT = 'intervention_attachment_create';

  /**
   * Constant LIST_INTERVENTION_ATTACHMENTS.
   *
   * @var string
   */
  public const string LIST_INTERVENTION_ATTACHMENTS = 'intervention_attachment_list';

  /**
   * Constant GET_INTERVENTION_ATTACHMENT.
   *
   * @var string
   */
  public const string GET_INTERVENTION_ATTACHMENT = 'intervention_attachment_get';

  /**
   * Constant DELETE_INTERVENTION_ATTACHMENT.
   *
   * @var string
   */
  public const string DELETE_INTERVENTION_ATTACHMENT = 'intervention_attachment_delete';

  /**
   * Constant DOWNLOAD_INTERVENTION_ATTACHMENT.
   *
   * @var string
   */
  public const string DOWNLOAD_INTERVENTION_ATTACHMENT = 'download_intervention_attachment';

  // Intervention changes
  /**
   * Constant CREATE_INTERVENTION_CHANGE.
   *
   * @var string
   */
  public const string CREATE_INTERVENTION_CHANGE = 'intervention_change_create';

  /**
   * Constant PUT_INTERVENTION_CHANGE.
   *
   * @var string
   */
  public const string PUT_INTERVENTION_CHANGE = 'intervention_change_put';

  /**
   * Constant LIST_INTERVENTION_CHANGES.
   *
   * @var string
   */
  public const string LIST_INTERVENTION_CHANGES = 'intervention_change_list';

  /**
   * Constant GET_INTERVENTION_CHANGE.
   *
   * @var string
   */
  public const string GET_INTERVENTION_CHANGE = 'intervention_change_get';

  /**
   * Constant UPDATE_INTERVENTION_CHANGE.
   *
   * @var string
   */
  public const string UPDATE_INTERVENTION_CHANGE = 'intervention_change_update';

  /**
   * Constant DELETE_INTERVENTION_CHANGE.
   *
   * @var string
   */
  public const string DELETE_INTERVENTION_CHANGE = 'intervention_change_delete';

  // Intervention issues (read-only diagnostics)
  /**
   * Constant LIST_INTERVENTION_ISSUES.
   *
   * @var string
   */
  public const string LIST_INTERVENTION_ISSUES = 'intervention_issue_list';

  // Intervention labels
  /**
   * Constant CREATE_INTERVENTION_LABEL.
   *
   * @var string
   */
  public const string CREATE_INTERVENTION_LABEL = 'intervention_label_create';

  /**
   * Constant LIST_INTERVENTION_LABELS.
   *
   * @var string
   */
  public const string LIST_INTERVENTION_LABELS = 'intervention_label_list';

  /**
   * Constant UPDATE_INTERVENTION_LABEL.
   *
   * @var string
   */
  public const string UPDATE_INTERVENTION_LABEL = 'intervention_label_update';

  /**
   * Constant DELETE_INTERVENTION_LABEL.
   *
   * @var string
   */
  public const string DELETE_INTERVENTION_LABEL = 'intervention_label_delete';

  // Intervention recurrences
  /**
   * Constant CREATE_INTERVENTION_RECURRENCE.
   *
   * @var string
   */
  public const string CREATE_INTERVENTION_RECURRENCE = 'intervention_recurrence_create';

  /**
   * Constant LIST_INTERVENTION_RECURRENCES.
   *
   * @var string
   */
  public const string LIST_INTERVENTION_RECURRENCES = 'intervention_recurrence_list';

  /**
   * Constant GET_INTERVENTION_RECURRENCE.
   *
   * @var string
   */
  public const string GET_INTERVENTION_RECURRENCE = 'intervention_recurrence_get';

  /**
   * Constant UPDATE_INTERVENTION_RECURRENCE.
   *
   * @var string
   */
  public const string UPDATE_INTERVENTION_RECURRENCE = 'intervention_recurrence_update';

  /**
   * Constant DELETE_INTERVENTION_RECURRENCE.
   *
   * @var string
   */
  public const string DELETE_INTERVENTION_RECURRENCE = 'intervention_recurrence_delete';

  // Intervention templates
  /**
   * Constant CREATE_INTERVENTION_TEMPLATE.
   *
   * @var string
   */
  public const string CREATE_INTERVENTION_TEMPLATE = 'intervention_template_create';

  /**
   * Constant LIST_INTERVENTION_TEMPLATES.
   *
   * @var string
   */
  public const string LIST_INTERVENTION_TEMPLATES = 'intervention_template_list';

  /**
   * Constant GET_INTERVENTION_TEMPLATE.
   *
   * @var string
   */
  public const string GET_INTERVENTION_TEMPLATE = 'intervention_template_get';

  /**
   * Constant UPDATE_INTERVENTION_TEMPLATE.
   *
   * @var string
   */
  public const string UPDATE_INTERVENTION_TEMPLATE = 'intervention_template_update';

  /**
   * Constant DELETE_INTERVENTION_TEMPLATE.
   *
   * @var string
   */
  public const string DELETE_INTERVENTION_TEMPLATE = 'intervention_template_delete';

  /**
   * Constant INSTANTIATE_INTERVENTION_TEMPLATE.
   *
   * @var string
   */
  public const string INSTANTIATE_INTERVENTION_TEMPLATE = 'intervention_template_instantiate';

  // Intervention work items
  /**
   * Constant CREATE_INTERVENTION_WORK_ITEM.
   *
   * @var string
   */
  public const string CREATE_INTERVENTION_WORK_ITEM = 'intervention_work_item_create';

  /**
   * Constant PUT_INTERVENTION_WORK_ITEM.
   *
   * @var string
   */
  public const string PUT_INTERVENTION_WORK_ITEM = 'intervention_work_item_put';

  /**
   * Constant LIST_INTERVENTION_WORK_ITEMS.
   *
   * @var string
   */
  public const string LIST_INTERVENTION_WORK_ITEMS = 'intervention_work_item_list';

  /**
   * Constant GET_INTERVENTION_WORK_ITEM.
   *
   * @var string
   */
  public const string GET_INTERVENTION_WORK_ITEM = 'intervention_work_item_get';

  /**
   * Constant UPDATE_INTERVENTION_WORK_ITEM.
   *
   * @var string
   */
  public const string UPDATE_INTERVENTION_WORK_ITEM = 'intervention_work_item_update';

  /**
   * Constant DELETE_INTERVENTION_WORK_ITEM.
   *
   * @var string
   */
  public const string DELETE_INTERVENTION_WORK_ITEM = 'intervention_work_item_delete';

  // Publications (the intervention publication write path)
  /**
   * Constant CREATE_PUBLICATION.
   *
   * @var string
   */
  public const string CREATE_PUBLICATION = 'publication_create';

  /**
   * Constant GET_PUBLICATION.
   *
   * @var string
   */
  public const string GET_PUBLICATION = 'publication_get';
  // #endregion
}
