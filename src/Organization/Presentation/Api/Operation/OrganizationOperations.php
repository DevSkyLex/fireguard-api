<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Operation;

/**
 * Operation OrganizationOperations.
 *
 * @category Operation
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOperations
{
  /**
   * Constant CREATE_ORGANIZATION
   */
  public const string CREATE_ORGANIZATION = 'createOrganization';

  /**
   * Constant LIST_ORGANIZATION_LEGAL_TYPES
   */
  public const string LIST_ORGANIZATION_LEGAL_TYPES = 'listOrganizationLegalTypes';

  /**
   * Constant LIST_USER_ORGANIZATIONS
   */
  public const string LIST_USER_ORGANIZATIONS = 'listUserOrganizations';

  /**
   * Constant GET_ORGANIZATION
   */
  public const string GET_ORGANIZATION = 'getOrganization';

  /**
   * Constant UPDATE_ORGANIZATION_SETTINGS
   */
  public const string UPDATE_ORGANIZATION_SETTINGS = 'updateOrganizationSettings';

  /**
   * Constant CHANGE_ORGANIZATION_PLAN
   */
  public const string CHANGE_ORGANIZATION_PLAN = 'changeOrganizationPlan';

  /**
   * Constant GET_ORGANIZATION_QUOTA
   */
  public const string GET_ORGANIZATION_QUOTA = 'getOrganizationQuota';

  /**
   * Constant DELETE_ORGANIZATION
   */
  public const string DELETE_ORGANIZATION = 'deleteOrganization';

  /**
   * Constant SUSPEND_ORGANIZATION
   */
  public const string SUSPEND_ORGANIZATION = 'suspendOrganization';

  /**
   * Constant RESTORE_ORGANIZATION
   */
  public const string RESTORE_ORGANIZATION = 'restoreOrganization';

  /**
   * Constant TRANSFER_ORGANIZATION_OWNERSHIP
   */
  public const string TRANSFER_ORGANIZATION_OWNERSHIP = 'transferOrganizationOwnership';

  /**
   * Constant UPLOAD_ORGANIZATION_LOGO
   */
  public const string UPLOAD_ORGANIZATION_LOGO = 'uploadOrganizationLogo';

  /**
   * Constant REMOVE_ORGANIZATION_LOGO
   */
  public const string REMOVE_ORGANIZATION_LOGO = 'removeOrganizationLogo';

  /**
   * Constant GET_ORGANIZATION_LOGO
   */
  public const string GET_ORGANIZATION_LOGO = 'getOrganizationLogo';

  /**
   * Constant GET_CURRENT_ORGANIZATION_MEMBER_PROFILE
   */
  public const string GET_CURRENT_ORGANIZATION_MEMBER_PROFILE = 'getCurrentOrganizationMemberProfile';

  /**
   * Constant GET_ORGANIZATION_DASHBOARD
   */
  public const string GET_ORGANIZATION_DASHBOARD = 'getOrganizationDashboard';

  /**
   * Constant GET_ORGANIZATION_NAVIGATION_COUNTERS
   */
  public const string GET_ORGANIZATION_NAVIGATION_COUNTERS = 'getOrganizationNavigationCounters';

  /**
   * Constant SEARCH_ORGANIZATION
   */
  public const string SEARCH_ORGANIZATION = 'searchOrganization';

  /**
   * Constant LIST_ORGANIZATION_AUDIT_EVENTS
   */
  public const string LIST_ORGANIZATION_AUDIT_EVENTS = 'listOrganizationAuditEvents';

  /**
   * Constant EXPORT_ORGANIZATION_AUDIT_EVENTS
   */
  public const string EXPORT_ORGANIZATION_AUDIT_EVENTS = 'exportOrganizationAuditEvents';

  /**
   * Constant GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND
   */
  public const string GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND = 'getOrganizationDashboardInspectionsTrend';

  /**
   * Constant GET_ORGANIZATION_DASHBOARD_EQUIPMENT_CREATED_TREND
   */
  public const string GET_ORGANIZATION_DASHBOARD_EQUIPMENT_CREATED_TREND = 'getOrganizationDashboardEquipmentCreatedTrend';

  /**
   * Constant GET_ORGANIZATION_DASHBOARD_FACILITIES_CREATED_TREND
   */
  public const string GET_ORGANIZATION_DASHBOARD_FACILITIES_CREATED_TREND = 'getOrganizationDashboardFacilitiesCreatedTrend';

  /**
   * Constant GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_OPENED_TREND
   */
  public const string GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_OPENED_TREND = 'getOrganizationDashboardNonConformitiesOpenedTrend';

  /**
   * Constant GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_RESOLVED_TREND
   */
  public const string GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_RESOLVED_TREND = 'getOrganizationDashboardNonConformitiesResolvedTrend';

  /**
   * Constant ADD_ORGANIZATION_MEMBER
   */
  public const string ADD_ORGANIZATION_MEMBER = 'addOrganizationMember';

  /**
   * Constant LIST_ORGANIZATION_MEMBERS
   */
  public const string LIST_ORGANIZATION_MEMBERS = 'listOrganizationMembers';

  /**
   * Constant GET_ORGANIZATION_MEMBER
   */
  public const string GET_ORGANIZATION_MEMBER = 'getOrganizationMember';

  /**
   * Constant REACTIVATE_ORGANIZATION_MEMBER
   */
  public const string REACTIVATE_ORGANIZATION_MEMBER = 'reactivateOrganizationMember';

  /**
   * Constant SET_ORGANIZATION_MEMBER_ROLES
   */
  public const string SET_ORGANIZATION_MEMBER_ROLES = 'setOrganizationMemberRoles';

  /**
   * Constant CREATE_ORGANIZATION_ROLE
   */
  public const string CREATE_ORGANIZATION_ROLE = 'createOrganizationRole';

  /**
   * Constant UPDATE_ORGANIZATION_ROLE
   */
  public const string UPDATE_ORGANIZATION_ROLE = 'updateOrganizationRole';

  /**
   * Constant GET_ORGANIZATION_ROLE
   */
  public const string GET_ORGANIZATION_ROLE = 'getOrganizationRole';

  /**
   * Constant LIST_ORGANIZATION_ROLES
   */
  public const string LIST_ORGANIZATION_ROLES = 'listOrganizationRoles';

  /**
   * Constant LIST_ORGANIZATION_PERMISSIONS
   */
  public const string LIST_ORGANIZATION_PERMISSIONS = 'listOrganizationPermissions';

  /**
   * Constant ASSIGN_ORGANIZATION_ROLE_TO_MEMBER
   */
  public const string ASSIGN_ORGANIZATION_ROLE_TO_MEMBER = 'assignOrganizationRoleToMember';

  /**
   * Constant REMOVE_ORGANIZATION_MEMBER
   */
  public const string REMOVE_ORGANIZATION_MEMBER = 'removeOrganizationMember';

  /**
   * Constant LEAVE_ORGANIZATION
   */
  public const string LEAVE_ORGANIZATION = 'leaveOrganization';

  /**
   * Constant BATCH_REMOVE_ORGANIZATION_MEMBERS
   */
  public const string BATCH_REMOVE_ORGANIZATION_MEMBERS = 'batchRemoveOrganizationMembers';

  /**
   * Constant REMOVE_ORGANIZATION_ROLE_FROM_MEMBER
   */
  public const string REMOVE_ORGANIZATION_ROLE_FROM_MEMBER = 'removeOrganizationRoleFromMember';

  /**
   * Constant DELETE_ORGANIZATION_ROLE
   */
  public const string DELETE_ORGANIZATION_ROLE = 'deleteOrganizationRole';

  /**
   * Constant INVITE_ORGANIZATION_MEMBER
   */
  public const string INVITE_ORGANIZATION_MEMBER = 'inviteOrganizationMember';

  /**
   * Constant LIST_ORGANIZATION_INVITATIONS
   */
  public const string LIST_ORGANIZATION_INVITATIONS = 'listOrganizationInvitations';

  /**
   * Constant ACCEPT_ORGANIZATION_INVITATION
   */
  public const string ACCEPT_ORGANIZATION_INVITATION = 'acceptOrganizationInvitation';

  /**
   * Constant REVOKE_ORGANIZATION_INVITATION
   */
  public const string REVOKE_ORGANIZATION_INVITATION = 'revokeOrganizationInvitation';

  /**
   * Constant RESEND_ORGANIZATION_INVITATION
   */
  public const string RESEND_ORGANIZATION_INVITATION = 'resendOrganizationInvitation';

  /**
   * Constant GET_ORGANIZATION_INVITATION_PREVIEW
   */
  public const string GET_ORGANIZATION_INVITATION_PREVIEW = 'getOrganizationInvitationPreview';

  /**
   * Constant CREATE_TEAM
   */
  public const string CREATE_TEAM = 'createTeam';

  /**
   * Constant LIST_TEAMS
   */
  public const string LIST_TEAMS = 'listTeams';

  /**
   * Constant GET_TEAM
   */
  public const string GET_TEAM = 'getTeam';

  /**
   * Constant UPDATE_TEAM
   */
  public const string UPDATE_TEAM = 'updateTeam';

  /**
   * Constant DELETE_TEAM
   */
  public const string DELETE_TEAM = 'deleteTeam';

  /**
   * Constant ADD_TEAM_MEMBER
   */
  public const string ADD_TEAM_MEMBER = 'addTeamMember';

  /**
   * Constant REMOVE_TEAM_MEMBER
   */
  public const string REMOVE_TEAM_MEMBER = 'removeTeamMember';

  /**
   * Constant LIST_TEAM_MEMBERS
   */
  public const string LIST_TEAM_MEMBERS = 'listTeamMembers';
}
