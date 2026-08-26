<?php

declare(strict_types=1);

namespace Calendar\Application\UseCase\Query\Feed\GetCalendarFeed;

use Calendar\Application\Service\CalendarFeedAggregator;
use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\InvalidValueException;

use function preg_match;
use function sprintf;
use function str_pad;
use function substr;

/**
 * UseCase GetCalendarFeedHandler.
 *
 * Enforces `organization.events.read`, parses and validates the mandatory
 * `from`/`to` bounds, and delegates the merge to {@see CalendarFeedAggregator}.
 * The date range is mandatory and bounded (max 366 days, mirroring
 * `Organization\Application\Support\DashboardSeriesBuilder::MAX_TREND_PERIOD_DAYS`)
 * so a busy organization's history can never be loaded in a single request —
 * see `Calendar\MODULE.md`.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCalendarFeedHandler implements QueryHandler
{
  // #region Constants
  private const string READ_PERMISSION = 'organization.events.read';

  /**
   * Constant MAX_FEED_SPAN_DAYS.
   *
   * Longest date range the feed will serve, in days. Mirrors the dashboard's
   * `MAX_TREND_PERIOD_DAYS` cap.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int MAX_FEED_SPAN_DAYS = 366;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param CalendarFeedAggregator $aggregator the calendar feed aggregator service
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private CalendarFeedAggregator $aggregator,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetCalendarFeedQuery $query the query payload
   *
   * @throws InvalidArgumentException when `from`/`to` are malformed, inverted, or span more than 366 days
   *
   * @return GetCalendarFeedResult the query result
   */
  public function __invoke(GetCalendarFeedQuery $query): GetCalendarFeedResult
  {
    $this->authorization->assertGrantedPermissions($query->userId, $query->organizationId, [
      self::READ_PERMISSION,
    ]);

    $from = self::parse($query->from, 'from');
    $to = self::parse($query->to, 'to');
    self::assertSupportedRange($from, $to);

    $items = $this->aggregator->aggregate($query->organizationId, $from, $to);

    return new GetCalendarFeedResult(items: $items, from: $from, to: $to);
  }

  /**
   * Method parse.
   *
   * @static
   *
   * Parses a mandatory ISO-8601 datetime filter with an explicit timezone
   * offset, mirroring `Organization\Application\Support\DashboardDateTimeParser::parse()`.
   *
   * @since 1.0.0
   *
   * @param string $value the raw filter value
   * @param string $filterName the filter name, for the error message
   *
   * @throws InvalidArgumentException when the value is not a valid ISO-8601 datetime
   *
   * @return DateTimeImmutable the parsed datetime
   */
  private static function parse(string $value, string $filterName): DateTimeImmutable
  {
    if (!preg_match(
      '/^(?<date>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?<fraction>\.\d{1,6})?(?<timezone>Z|[+-]\d{2}:\d{2})$/',
      $value,
      $matches,
    )) {
      throw InvalidValueException::because(sprintf(
        'Invalid "%s" datetime filter. Use an ISO 8601 datetime with an explicit timezone offset.',
        $filterName,
      ));
    }

    $fraction = $matches['fraction'];
    if ('' !== $fraction) {
      $fraction = '.' . str_pad(substr($fraction, 1), 6, '0');
    }

    $timeZone = $matches['timezone'];
    $normalizedValue = $matches['date'] . $fraction . ('Z' === $timeZone ? '+00:00' : $timeZone);
    $format = '' === $fraction ? '!Y-m-d\TH:i:sP' : '!Y-m-d\TH:i:s.uP';
    $dateTime = DateTimeImmutable::createFromFormat($format, $normalizedValue);

    $errors = DateTimeImmutable::getLastErrors();
    if (
      false === $dateTime
      || (false !== $errors && (0 !== $errors['warning_count'] || 0 !== $errors['error_count']))
    ) {
      throw InvalidValueException::because(sprintf(
        'Invalid "%s" datetime filter. Use an ISO 8601 datetime with an explicit timezone offset.',
        $filterName,
      ));
    }

    return $dateTime;
  }

  /**
   * Method assertSupportedRange.
   *
   * @static
   *
   * Rejects inverted and over-long ranges.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $from the range lower bound
   * @param DateTimeImmutable $to the range upper bound
   *
   * @throws InvalidArgumentException when the range is inverted or exceeds 366 days
   */
  private static function assertSupportedRange(DateTimeImmutable $from, DateTimeImmutable $to): void
  {
    if ($from->getTimestamp() > $to->getTimestamp()) {
      throw InvalidValueException::because('The "from" datetime filter must be before or equal to "to".');
    }
    if ((int) $from->setTime(0, 0)->diff($to->setTime(0, 0))->days + 1 > self::MAX_FEED_SPAN_DAYS) {
      throw InvalidValueException::because('Calendar feed date range cannot exceed 366 days.');
    }
  }
  // #endregion
}
