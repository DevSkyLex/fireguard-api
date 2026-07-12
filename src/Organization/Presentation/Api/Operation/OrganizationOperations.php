<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Operation;

/**
 * Operation OrganizationOperations.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOperations
{
  public const string CREATE_ORGANIZATION = 'createOrganization';

  public const string LIST_ORGANIZATION_STATUSES = 'listOrganizationStatuses';

  public const string LIST_ORGANIZATION_INVITATION_STATUSES = 'listOrganizationInvitationStatuses';

  public const string LIST_USER_ORGANIZATIONS = 'listUserOrganizations';

  public const string GET_ORGANIZATION = 'getOrganization';

  public const string UPDATE_ORGANIZATION_SETTINGS = 'updateOrganizationSettings';

  public const string CHANGE_ORGANIZATION_PLAN = 'changeOrganizationPlan';

  public const string GET_ORGANIZATION_QUOTA = 'getOrganizationQuota';

  public const string DELETE_ORGANIZATION = 'deleteOrganization';

  public const string UPLOAD_ORGANIZATION_LOGO = 'uploadOrganizationLogo';

  public const string GET_ORGANIZATION_LOGO = 'getOrganizationLogo';

  public const string GET_CURRENT_ORGANIZATION_MEMBER_PROFILE = 'getCurrentOrganizationMemberProfile';

  public const string GET_ORGANIZATION_DASHBOARD = 'getOrganizationDashboard';

  public const string GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND = 'getOrganizationDashboardInspectionsTrend';

  public const string GET_ORGANIZATION_DASHBOARD_EQUIPMENT_CREATED_TREND = 'getOrganizationDashboardEquipmentCreatedTrend';

  public const string GET_ORGANIZATION_DASHBOARD_FACILITIES_CREATED_TREND = 'getOrganizationDashboardFacilitiesCreatedTrend';

  public const string GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_OPENED_TREND = 'getOrganizationDashboardNonConformitiesOpenedTrend';

  public const string GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_RESOLVED_TREND = 'getOrganizationDashboardNonConformitiesResolvedTrend';

  public const string ADD_ORGANIZATION_MEMBER = 'addOrganizationMember';

  public const string LIST_ORGANIZATION_MEMBERS = 'listOrganizationMembers';

  public const string CREATE_ORGANIZATION_ROLE = 'createOrganizationRole';

  public const string UPDATE_ORGANIZATION_ROLE = 'updateOrganizationRole';

  public const string LIST_ORGANIZATION_ROLES = 'listOrganizationRoles';

  public const string LIST_ORGANIZATION_PERMISSIONS = 'listOrganizationPermissions';

  public const string ASSIGN_ORGANIZATION_ROLE_TO_MEMBER = 'assignOrganizationRoleToMember';

  public const string REMOVE_ORGANIZATION_MEMBER = 'removeOrganizationMember';

  public const string BATCH_REMOVE_ORGANIZATION_MEMBERS = 'batchRemoveOrganizationMembers';

  public const string REMOVE_ORGANIZATION_ROLE_FROM_MEMBER = 'removeOrganizationRoleFromMember';

  public const string DELETE_ORGANIZATION_ROLE = 'deleteOrganizationRole';

  public const string INVITE_ORGANIZATION_MEMBER = 'inviteOrganizationMember';

  public const string LIST_ORGANIZATION_INVITATIONS = 'listOrganizationInvitations';

  public const string ACCEPT_ORGANIZATION_INVITATION = 'acceptOrganizationInvitation';

  public const string REVOKE_ORGANIZATION_INVITATION = 'revokeOrganizationInvitation';

  public const string RESEND_ORGANIZATION_INVITATION = 'resendOrganizationInvitation';

  public const string GET_ORGANIZATION_INVITATION_PREVIEW = 'getOrganizationInvitationPreview';
}
