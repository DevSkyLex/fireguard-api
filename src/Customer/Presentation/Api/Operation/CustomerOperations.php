<?php

declare(strict_types=1);

namespace Customer\Presentation\Api\Operation;

/** Class CustomerOperations. Stable operation identifiers for customer API. @category Operation */
final class CustomerOperations
{
  public const string LIST = 'list_customers';

  public const string GET = 'get_customer';

  public const string CREATE = 'create_customer';

  public const string PATCH = 'patch_customer';

  public const string ARCHIVE = 'archive_customer';

  public const string RESTORE = 'restore_customer';
}
