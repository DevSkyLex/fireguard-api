<?php

declare(strict_types=1);

namespace Customer\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class CustomerHttpIdempotencyOperationContributionAdapter.
 *
 * Declares every owning main-database mutation and its exact immutable public-field result graph.
 *
 * @category Adapter
 */
final readonly class CustomerHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * Method definitions.
   *
   * @access public
   *
   * @return list<HttpIdempotencyOperation> explicit HTTP mutations; pure planning assessments remain reads
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'create_customer',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Customer\Application\UseCase\Command\CreateCustomer\CreateCustomerCommand::class,
        resultClass: \Customer\Application\UseCase\Command\CreateCustomer\CreateCustomerResult::class,
        contractClasses: [\Customer\Application\Contract\CustomerView::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'customer.id',
        resourceKind: 'customer',
      ),
      new HttpIdempotencyOperation(
        name: 'patch_customer',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Customer\Application\UseCase\Command\ChangeCustomer\ChangeCustomerCommand::class,
        resultClass: \Customer\Application\UseCase\Command\ChangeCustomer\ChangeCustomerResult::class,
        contractClasses: [\Customer\Application\Contract\CustomerView::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'customer.id',
        resourceKind: 'customer',
      ),
      new HttpIdempotencyOperation(
        name: 'archive_customer',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Customer\Application\UseCase\Command\ChangeCustomer\ChangeCustomerCommand::class,
        resultClass: \Customer\Application\UseCase\Command\ChangeCustomer\ChangeCustomerResult::class,
        contractClasses: [\Customer\Application\Contract\CustomerView::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'customer.id',
        resourceKind: 'customer',
      ),
      new HttpIdempotencyOperation(
        name: 'restore_customer',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Customer\Application\UseCase\Command\ChangeCustomer\ChangeCustomerCommand::class,
        resultClass: \Customer\Application\UseCase\Command\ChangeCustomer\ChangeCustomerResult::class,
        contractClasses: [\Customer\Application\Contract\CustomerView::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'customer.id',
        resourceKind: 'customer',
      ),
    ];
  }
  // #endregion
}
