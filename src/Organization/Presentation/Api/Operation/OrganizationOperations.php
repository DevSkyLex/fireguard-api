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

  public const string LIST_ORGANIZATION_COUNTRIES = 'listOrganizationCountries';

  public const string LIST_ORGANIZATION_LEGAL_TYPES = 'listOrganizationLegalTypes';

  public const string GET_ORGANIZATION_LEGAL_PROFILE = 'getOrganizationLegalProfile';

  public const string UPSERT_ORGANIZATION_LEGAL_PROFILE = 'upsertOrganizationLegalProfile';

  public const string LIST_USER_ORGANIZATIONS = 'listUserOrganizations';

  public const string GET_ORGANIZATION = 'getOrganization';

  public const string GET_ORGANIZATION_STATISTICS = 'getOrganizationStatistics';

  public const string ADD_ORGANIZATION_MEMBER = 'addOrganizationMember';

  public const string LIST_ORGANIZATION_MEMBERS = 'listOrganizationMembers';

  public const string CREATE_ORGANIZATION_ROLE = 'createOrganizationRole';

  public const string UPDATE_ORGANIZATION_ROLE = 'updateOrganizationRole';

  public const string LIST_ORGANIZATION_ROLES = 'listOrganizationRoles';

  public const string LIST_ORGANIZATION_PERMISSIONS = 'listOrganizationPermissions';

  public const string ASSIGN_ORGANIZATION_ROLE_TO_MEMBER = 'assignOrganizationRoleToMember';

  public const string REMOVE_ORGANIZATION_MEMBER = 'removeOrganizationMember';

  public const string REMOVE_ORGANIZATION_ROLE_FROM_MEMBER = 'removeOrganizationRoleFromMember';

  public const string DELETE_ORGANIZATION_ROLE = 'deleteOrganizationRole';

  public const string INVITE_ORGANIZATION_MEMBER = 'inviteOrganizationMember';

  public const string LIST_ORGANIZATION_INVITATIONS = 'listOrganizationInvitations';

  public const string ACCEPT_ORGANIZATION_INVITATION = 'acceptOrganizationInvitation';

  public const string REVOKE_ORGANIZATION_INVITATION = 'revokeOrganizationInvitation';
}
