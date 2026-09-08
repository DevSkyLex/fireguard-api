<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Operation;

/**
 * Operation OrganizationJoinOperations.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationJoinOperations
{
  public const string JOIN_OPTIONS = 'organization_join_join_options';

  public const string MY_REQUESTS = 'organization_join_my_requests';

  public const string ACCESS_POLICY = 'organization_join_access_policy';

  public const string UPDATE_ACCESS_POLICY = 'organization_join_update_access_policy';

  public const string ADD_DOMAIN = 'organization_join_add_domain';

  public const string VERIFY_DOMAIN = 'organization_join_verify_domain';

  public const string REMOVE_DOMAIN = 'organization_join_remove_domain';

  public const string JOIN = 'organization_join_join';

  public const string REQUEST = 'organization_join_request';

  public const string ORGANIZATION_REQUESTS = 'organization_join_organization_requests';

  public const string APPROVE_REQUEST = 'organization_join_approve_request';

  public const string REJECT_REQUEST = 'organization_join_reject_request';

  public const string CANCEL_REQUEST = 'organization_join_cancel_request';

  public const string ACCEPT_INVITATION_BY_ID = 'organization_join_accept_invitation_by_id';
}
