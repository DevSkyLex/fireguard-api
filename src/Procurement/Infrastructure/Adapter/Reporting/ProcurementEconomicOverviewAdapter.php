<?php

declare(strict_types=1);

namespace Procurement\Infrastructure\Adapter\Reporting;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use MaintenanceCost\Application\Port\Inbound\MaintenanceCurrencyPort;
use Procurement\Application\Contract\Reporting\{ProcurementEconomicAmount, ProcurementEconomicOverview, ProcurementEconomicOverviewUnavailable};
use Procurement\Application\Port\Inbound\ProcurementEconomicOverviewPort;

use function bcadd;

/**
 * Class ProcurementEconomicOverviewAdapter
 *
 * Owns organization-scoped purchase aggregation; PostgreSQL numeric arithmetic never passes through binary floats.
 *
 * @category Adapter
 */
final readonly class ProcurementEconomicOverviewAdapter implements ProcurementEconomicOverviewPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param Connection $connection explicitly configured main connection
   * @param MaintenanceCurrencyPort $currencies public organization currency reader
   *
   * @return void
   */
  public function __construct(private Connection $connection, private MaintenanceCurrencyPort $currencies)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method overview
   *
   * Bounds the order set before expanding lines and counts retained receipt facts once, independently of return reconciliation.
   *
   * @access public
   *
   * @param string $organizationId trusted financial reporting scope
   * @param DateTimeImmutable $from inclusive order-creation instant
   * @param DateTimeImmutable $to exclusive order-creation instant
   *
   * @return ProcurementEconomicOverview exact order-window report
   *
   * @throws ProcurementEconomicOverviewUnavailable when the selection cannot be represented reliably
   */
  public function overview(string $organizationId, DateTimeImmutable $from, DateTimeImmutable $to): ProcurementEconomicOverview
  {
    if ($from >= $to) {
      throw new ProcurementEconomicOverviewUnavailable('invalid_period', 'The procurement reporting period needs an exclusive end after its start.');
    }
    $currency = $this->currencies->forOrganization($organizationId);
    $utc = new DateTimeZone('UTC');
    /** @var array{order_count:int|string,order_currency_count:int|string,order_currency:?string,receipt_count:int|string,receipt_currency_count:int|string,receipt_currency:?string,pending_individualization_count:int|string,ordered_known:numeric-string,ordered_unknown:int|string,outstanding_known:numeric-string,outstanding_unknown:int|string,received_known:numeric-string,received_unknown:int|string,returned_known:numeric-string,returned_unknown:int|string} $row */
    $row = $this->connection->fetchAssociative(<<<'SQL'
      WITH selected_orders AS MATERIALIZED (
        SELECT id, currency, status, lines
        FROM procurement_orders
        WHERE organization_id = :organization
          AND status <> 'draft' AND created_at >= :from AND created_at < :to
        ORDER BY created_at ASC, id ASC
        LIMIT 501
      ), order_metrics AS (
        SELECT COUNT(*) AS order_count, COUNT(DISTINCT currency) AS order_currency_count,
          MIN(currency) AS order_currency
        FROM selected_orders
      ), line_values AS (
        SELECT CASE WHEN o.status = 'cancelled' THEN (l.value->>'receivedQuantity')::numeric
            ELSE (l.value->>'quantity')::numeric END AS committed,
          CASE WHEN o.status = 'cancelled' THEN 0::numeric
            ELSE (l.value->>'quantity')::numeric - (l.value->>'receivedQuantity')::numeric END AS remaining,
          (l.value->>'unitCost')::numeric AS unit_cost
        FROM selected_orders o CROSS JOIN LATERAL jsonb_array_elements(o.lines) AS l(value)
      ), line_metrics AS (
        SELECT COALESCE(SUM(ROUND(committed * unit_cost, 6)), 0)::text AS ordered_known,
          COUNT(*) FILTER (WHERE committed > 0 AND unit_cost IS NULL) AS ordered_unknown,
          COALESCE(SUM(ROUND(remaining * unit_cost, 6)), 0)::text AS outstanding_known,
          COUNT(*) FILTER (WHERE remaining > 0 AND unit_cost IS NULL) AS outstanding_unknown
        FROM line_values
      ), receipt_metrics AS (
        SELECT COUNT(*) AS receipt_count, COUNT(DISTINCT r.currency) AS receipt_currency_count,
          MIN(r.currency) AS receipt_currency,
          COUNT(*) FILTER (WHERE r.kind = 'equipment_to_individualize'
            AND jsonb_array_length(r.equipment_ids) = 0 AND r.quantity > r.returned_quantity) AS pending_individualization_count,
          COALESCE(SUM(ROUND(r.quantity * r.unit_cost, 6)), 0)::text AS received_known,
          COUNT(*) FILTER (WHERE r.quantity > 0 AND r.unit_cost IS NULL) AS received_unknown,
          COALESCE(SUM(ROUND(r.returned_quantity * r.unit_cost, 6)), 0)::text AS returned_known,
          COUNT(*) FILTER (WHERE r.returned_quantity > 0 AND r.unit_cost IS NULL) AS returned_unknown
        FROM procurement_receipts r JOIN selected_orders o ON o.id = r.order_id
        WHERE r.organization_id = :organization
      )
      SELECT * FROM order_metrics CROSS JOIN line_metrics CROSS JOIN receipt_metrics
      SQL, ['organization' => $organizationId, 'from' => $from->setTimezone($utc), 'to' => $to->setTimezone($utc)], ['from' => Types::DATETIME_IMMUTABLE, 'to' => Types::DATETIME_IMMUTABLE]);
    if ((int) $row['order_count'] > 500) {
      throw new ProcurementEconomicOverviewUnavailable('order_limit', 'The procurement overview exceeds 500 orders; narrow the reporting period.');
    }
    if ((int) $row['order_currency_count'] > 1 || (null !== $row['order_currency'] && $row['order_currency'] !== $currency)
      || (int) $row['receipt_currency_count'] > 1 || (null !== $row['receipt_currency'] && $row['receipt_currency'] !== $currency)) {
      throw new ProcurementEconomicOverviewUnavailable('mixed_currency', 'Purchase economics must use the configured organization currency.');
    }

    return new ProcurementEconomicOverview(
      $organizationId,
      $currency,
      $from->format('c'),
      $to->format('c'),
      (int) $row['order_count'],
      (int) $row['receipt_count'],
      (int) $row['pending_individualization_count'],
      $this->amount($row['ordered_known'], (int) $row['ordered_unknown']),
      $this->amount($row['received_known'], (int) $row['received_unknown']),
      $this->amount($row['outstanding_known'], (int) $row['outstanding_unknown']),
      $this->amount($row['returned_known'], (int) $row['returned_unknown']),
    );
  }

  /**
   * Method amount
   *
   * A zero quantity never makes a subtotal incomplete merely because its historical price was unknown.
   *
   * @access private
   *
   * @param numeric-string $known exact PostgreSQL numeric subtotal
   * @param int $unknown number of positive quantities with no price
   *
   * @return ProcurementEconomicAmount canonical six-place total or explicitly incomplete subtotal
   */
  private function amount(string $known, int $unknown): ProcurementEconomicAmount
  {
    $subtotal = bcadd($known, '0', 6);

    return new ProcurementEconomicAmount(0 === $unknown ? $subtotal : null, $subtotal, 0 === $unknown);
  }
  // #endregion
}
