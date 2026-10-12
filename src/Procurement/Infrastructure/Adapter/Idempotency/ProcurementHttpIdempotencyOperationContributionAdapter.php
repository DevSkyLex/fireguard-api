<?php

declare(strict_types=1);

namespace Procurement\Infrastructure\Adapter\Idempotency;

use Procurement\Application\UseCase\Command\ManageProcurement\{ManageProcurementCommand, ManageProcurementResult};
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class ProcurementHttpIdempotencyOperationContributionAdapter
 *
 * Declares exact owner command, database, transaction and response schemas for durable HTTP attempts.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author FireGuard contributors
 */
final readonly class ProcurementHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * {@inheritdoc}
   */
  public static function definitions(): array
  {
    return [
      self::operation(name: 'procurement_reconcile_return', resourceKind: 'return'),
      self::operation(name: 'procurement_create_supplier', resourceKind: 'supplier', statusCode: 201),
      self::operation(name: 'procurement_change_supplier', resourceKind: 'supplier'),
      self::operation(name: 'procurement_archive_supplier', resourceKind: 'supplier'),
      self::operation(name: 'procurement_create_order', resourceKind: 'purchase_order', statusCode: 201),
      self::operation(name: 'procurement_change_order', resourceKind: 'purchase_order'),
      self::operation(name: 'procurement_place_order', resourceKind: 'purchase_order'),
      self::operation(name: 'procurement_cancel_remaining', resourceKind: 'purchase_order'),
      self::operation(name: 'procurement_receive', resourceKind: 'receipt', statusCode: 201),
      self::operation(name: 'procurement_individualize', resourceKind: 'receipt'),
      self::operation(name: 'procurement_return', resourceKind: 'receipt'),
    ];
  }

  /**
   * Method operation
   *
   * Binds every procurement mutation to the same owning command and immutable result schema.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $name the stable HTTP operation name
   * @param string $resourceKind the owner-selected result lookup kind
   * @param int $statusCode the declared successful response status
   *
   * @return HttpIdempotencyOperation the complete owner operation descriptor
   */
  private static function operation(string $name, string $resourceKind, int $statusCode = 200): HttpIdempotencyOperation
  {
    return new HttpIdempotencyOperation(
      name: $name,
      database: 'main',
      transactionMode: 'bus_owner',
      primaryCommand: ManageProcurementCommand::class,
      resultClass: ManageProcurementResult::class,
      statusCode: $statusCode,
      requiredKey: true,
      resultIdentifierProperty: 'data.id',
      resourceKind: $resourceKind,
    );
  }
  // #endregion
}
