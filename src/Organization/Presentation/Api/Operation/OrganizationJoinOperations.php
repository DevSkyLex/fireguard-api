<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Operation;

/**
 * Operation OrganizationJoinOperations.
 *
 * @category Operation
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationJoinOperations
{
  /**
   * Constant JOIN_OPTIONS
   */
  public const string JOIN_OPTIONS = 'organization_join_join_options';

  /**
   * Constant MY_REQUESTS
   */
  public const string MY_REQUESTS = 'organization_join_my_requests';

  /**
   * Constant ACCESS_POLICY
   */
  public const string ACCESS_POLICY = 'organization_join_access_policy';

  /**
   * Constant UPDATE_ACCESS_POLICY
   */
  public const string UPDATE_ACCESS_POLICY = 'organization_join_update_access_policy';

  /**
   * Constant ADD_DOMAIN
   */
  public const string ADD_DOMAIN = 'organization_join_add_domain';

  /**
   * Constant VERIFY_DOMAIN
   */
  public const string VERIFY_DOMAIN = 'organization_join_verify_domain';

  /**
   * Constant REMOVE_DOMAIN
   */
  public const string REMOVE_DOMAIN = 'organization_join_remove_domain';

  /**
   * Constant JOIN
   */
  public const string JOIN = 'organization_join_join';

  /**
   * Constant REQUEST
   */
  public const string REQUEST = 'organization_join_request';

  /**
   * Constant ORGANIZATION_REQUESTS
   */
  public const string ORGANIZATION_REQUESTS = 'organization_join_organization_requests';

  /**
   * Constant APPROVE_REQUEST
   */
  public const string APPROVE_REQUEST = 'organization_join_approve_request';

  /**
   * Constant REJECT_REQUEST
   */
  public const string REJECT_REQUEST = 'organization_join_reject_request';

  /**
   * Constant CANCEL_REQUEST
   */
  public const string CANCEL_REQUEST = 'organization_join_cancel_request';

  /**
   * Constant ACCEPT_INVITATION_BY_ID
   */
  public const string ACCEPT_INVITATION_BY_ID = 'organization_join_accept_invitation_by_id';
}
