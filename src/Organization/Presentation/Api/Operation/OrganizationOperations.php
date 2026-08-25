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

  public const string LIST_ORGANIZATION_LEGAL_TYPES = 'listOrganizationLegalTypes';

  public const string LIST_USER_ORGANIZATIONS = 'listUserOrganizations';

  public const string GET_ORGANIZATION = 'getOrganization';

  public const string UPDATE_ORGANIZATION_SETTINGS = 'updateOrganizationSettings';

  public const string CHANGE_ORGANIZATION_PLAN = 'changeOrganizationPlan';

  public const string GET_ORGANIZATION_QUOTA = 'getOrganizationQuota';

  public const string DELETE_ORGANIZATION = 'deleteOrganization';

  public const string SUSPEND_ORGANIZATION = 'suspendOrganization';

  public const string RESTORE_ORGANIZATION = 'restoreOrganization';

  public const string TRANSFER_ORGANIZATION_OWNERSHIP = 'transferOrganizationOwnership';

  public const string UPLOAD_ORGANIZATION_LOGO = 'uploadOrganizationLogo';

  public const string REMOVE_ORGANIZATION_LOGO = 'removeOrganizationLogo';

  public const string GET_ORGANIZATION_LOGO = 'getOrganizationLogo';

  public const string GET_CURRENT_ORGANIZATION_MEMBER_PROFILE = 'getCurrentOrganizationMemberProfile';

  public const string GET_ORGANIZATION_DASHBOARD = 'getOrganizationDashboard';

  public const string GET_ORGANIZATION_NAVIGATION_COUNTERS = 'getOrganizationNavigationCounters';

  public const string LIST_ORGANIZATION_AUDIT_EVENTS = 'listOrganizationAuditEvents';

  public const string EXPORT_ORGANIZATION_AUDIT_EVENTS = 'exportOrganizationAuditEvents';

  public const string GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND = 'getOrganizationDashboardInspectionsTrend';

  public const string GET_ORGANIZATION_DASHBOARD_EQUIPMENT_CREATED_TREND = 'getOrganizationDashboardEquipmentCreatedTrend';

  public const string GET_ORGANIZATION_DASHBOARD_FACILITIES_CREATED_TREND = 'getOrganizationDashboardFacilitiesCreatedTrend';

  public const string GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_OPENED_TREND = 'getOrganizationDashboardNonConformitiesOpenedTrend';

  public const string GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_RESOLVED_TREND = 'getOrganizationDashboardNonConformitiesResolvedTrend';

  public const string ADD_ORGANIZATION_MEMBER = 'addOrganizationMember';

  public const string LIST_ORGANIZATION_MEMBERS = 'listOrganizationMembers';

  public const string GET_ORGANIZATION_MEMBER = 'getOrganizationMember';

  public const string REACTIVATE_ORGANIZATION_MEMBER = 'reactivateOrganizationMember';

  public const string SET_ORGANIZATION_MEMBER_ROLES = 'setOrganizationMemberRoles';

  public const string CREATE_ORGANIZATION_ROLE = 'createOrganizationRole';

  public const string UPDATE_ORGANIZATION_ROLE = 'updateOrganizationRole';

  public const string GET_ORGANIZATION_ROLE = 'getOrganizationRole';

  public const string LIST_ORGANIZATION_ROLES = 'listOrganizationRoles';

  public const string LIST_ORGANIZATION_PERMISSIONS = 'listOrganizationPermissions';

  public const string ASSIGN_ORGANIZATION_ROLE_TO_MEMBER = 'assignOrganizationRoleToMember';

  public const string REMOVE_ORGANIZATION_MEMBER = 'removeOrganizationMember';

  public const string LEAVE_ORGANIZATION = 'leaveOrganization';

  public const string BATCH_REMOVE_ORGANIZATION_MEMBERS = 'batchRemoveOrganizationMembers';

  public const string REMOVE_ORGANIZATION_ROLE_FROM_MEMBER = 'removeOrganizationRoleFromMember';

  public const string DELETE_ORGANIZATION_ROLE = 'deleteOrganizationRole';

  public const string INVITE_ORGANIZATION_MEMBER = 'inviteOrganizationMember';

  public const string LIST_ORGANIZATION_INVITATIONS = 'listOrganizationInvitations';

  public const string ACCEPT_ORGANIZATION_INVITATION = 'acceptOrganizationInvitation';

  public const string REVOKE_ORGANIZATION_INVITATION = 'revokeOrganizationInvitation';

  public const string RESEND_ORGANIZATION_INVITATION = 'resendOrganizationInvitation';

  public const string GET_ORGANIZATION_INVITATION_PREVIEW = 'getOrganizationInvitationPreview';

  public const string CREATE_TEAM = 'createTeam';

  public const string LIST_TEAMS = 'listTeams';

  public const string GET_TEAM = 'getTeam';

  public const string UPDATE_TEAM = 'updateTeam';

  public const string DELETE_TEAM = 'deleteTeam';

  public const string ADD_TEAM_MEMBER = 'addTeamMember';

  public const string REMOVE_TEAM_MEMBER = 'removeTeamMember';

  public const string LIST_TEAM_MEMBERS = 'listTeamMembers';
}
