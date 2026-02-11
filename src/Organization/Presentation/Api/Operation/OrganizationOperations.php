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

  public const string LIST_USER_ORGANIZATIONS = 'listUserOrganizations';

  public const string GET_ORGANIZATION = 'getOrganization';

  public const string ADD_ORGANIZATION_MEMBER = 'addOrganizationMember';

  public const string LIST_ORGANIZATION_MEMBERS = 'listOrganizationMembers';

  public const string CREATE_ORGANIZATION_ROLE = 'createOrganizationRole';

  public const string LIST_ORGANIZATION_ROLES = 'listOrganizationRoles';

  public const string ASSIGN_ORGANIZATION_ROLE_TO_MEMBER = 'assignOrganizationRoleToMember';
}
