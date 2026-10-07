<?php

declare(strict_types=1);

namespace ServiceRequest\Presentation\Api\Operation;

/** Class ServiceRequestOperations. Stable internal repair request operation names. @category Operation */
final class ServiceRequestOperations
{
  public const string LIST = 'list_service_requests';

  public const string GET = 'get_service_request';

  public const string CREATE = 'create_service_request';

  public const string PATCH = 'patch_service_request';

  public const string QUALIFY = 'qualify_service_request';

  public const string REJECT = 'reject_service_request';

  public const string CANCEL = 'cancel_service_request';

  public const string CONVERT = 'convert_service_request';
}
