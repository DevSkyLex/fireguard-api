<?php

declare(strict_types=1);

namespace ServiceRequest\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class ServiceRequestHttpIdempotencyOperationContributionAdapter.
 *
 * Declares every owning main-database mutation and its exact immutable public-field result graph.
 *
 * @category Adapter
 */
final readonly class ServiceRequestHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
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
        name: 'create_service_request',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \ServiceRequest\Application\UseCase\Command\CreateServiceRequest\CreateServiceRequestCommand::class,
        resultClass: \ServiceRequest\Application\UseCase\Command\CreateServiceRequest\CreateServiceRequestResult::class,
        contractClasses: [\ServiceRequest\Application\Contract\ServiceRequestView::class],
        statusCode: 201,
        requiredKey: true,
        resultIdentifierProperty: 'request.id',
        resourceKind: 'service_request',
      ),
      new HttpIdempotencyOperation(
        name: 'patch_service_request',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \ServiceRequest\Application\UseCase\Command\ChangeServiceRequest\ChangeServiceRequestCommand::class,
        resultClass: \ServiceRequest\Application\UseCase\Command\ChangeServiceRequest\ChangeServiceRequestResult::class,
        contractClasses: [\ServiceRequest\Application\Contract\ServiceRequestView::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'request.id',
        resourceKind: 'service_request',
      ),
      new HttpIdempotencyOperation(
        name: 'qualify_service_request',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \ServiceRequest\Application\UseCase\Command\ChangeServiceRequest\ChangeServiceRequestCommand::class,
        resultClass: \ServiceRequest\Application\UseCase\Command\ChangeServiceRequest\ChangeServiceRequestResult::class,
        contractClasses: [\ServiceRequest\Application\Contract\ServiceRequestView::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'request.id',
        resourceKind: 'service_request',
      ),
      new HttpIdempotencyOperation(
        name: 'reject_service_request',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \ServiceRequest\Application\UseCase\Command\ChangeServiceRequest\ChangeServiceRequestCommand::class,
        resultClass: \ServiceRequest\Application\UseCase\Command\ChangeServiceRequest\ChangeServiceRequestResult::class,
        contractClasses: [\ServiceRequest\Application\Contract\ServiceRequestView::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'request.id',
        resourceKind: 'service_request',
      ),
      new HttpIdempotencyOperation(
        name: 'cancel_service_request',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \ServiceRequest\Application\UseCase\Command\ChangeServiceRequest\ChangeServiceRequestCommand::class,
        resultClass: \ServiceRequest\Application\UseCase\Command\ChangeServiceRequest\ChangeServiceRequestResult::class,
        contractClasses: [\ServiceRequest\Application\Contract\ServiceRequestView::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'request.id',
        resourceKind: 'service_request',
      ),
      new HttpIdempotencyOperation(
        name: 'convert_service_request',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \ServiceRequest\Application\UseCase\Command\ConvertServiceRequest\ConvertServiceRequestCommand::class,
        resultClass: \ServiceRequest\Application\UseCase\Command\ConvertServiceRequest\ConvertServiceRequestResult::class,
        contractClasses: [\ServiceRequest\Application\Contract\ServiceRequestView::class],
        statusCode: 200,
        requiredKey: true,
        resultIdentifierProperty: 'request.id',
        resourceKind: 'service_request',
      ),
    ];
  }
  // #endregion
}
