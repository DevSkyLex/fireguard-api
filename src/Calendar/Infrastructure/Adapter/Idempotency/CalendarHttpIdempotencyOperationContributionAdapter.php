<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class CalendarHttpIdempotencyOperationContributionAdapter
 *
 * Declares exact owner mutations, transaction ownership and bounded replay result schemas.
 *
 * @category Adapter
 */
final readonly class CalendarHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<HttpIdempotencyOperation> registered owner mutations requiring client keys
   */
  public static function definitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'createCalendarEvent',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Calendar\Application\UseCase\Command\Event\CreateCalendarEvent\CreateCalendarEventCommand::class,
        resultClass: \Calendar\Application\UseCase\Command\Event\CreateCalendarEvent\CreateCalendarEventResult::class,
        statusCode: 201,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'calendar_event',
      ),
      new HttpIdempotencyOperation(
        name: 'updateCalendarEvent',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Calendar\Application\UseCase\Command\Event\UpdateCalendarEvent\UpdateCalendarEventCommand::class,
        resultClass: \Calendar\Application\UseCase\Command\Event\UpdateCalendarEvent\UpdateCalendarEventResult::class,
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'id',
        resourceKind: 'calendar_event',
      ),
      new HttpIdempotencyOperation(
        name: 'deleteCalendarEvent',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Calendar\Application\UseCase\Command\Event\DeleteCalendarEvent\DeleteCalendarEventCommand::class,
        resultClass: \Calendar\Application\UseCase\Command\Event\DeleteCalendarEvent\DeleteCalendarEventResult::class,
        statusCode: 204,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'eventId',
        resourceKind: 'calendar_event',
      ),
      new HttpIdempotencyOperation(
        name: 'createCalendarFeedToken',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Calendar\Application\UseCase\Command\FeedToken\RotateCalendarFeedToken\RotateCalendarFeedTokenCommand::class,
        resultClass: \Calendar\Application\UseCase\Command\FeedToken\RotateCalendarFeedToken\RotateCalendarFeedTokenResult::class,
        statusCode: 201,
        secret: true,
        requiredKey: true,
        resourceKind: 'calendar_feed_token',
      ),
      new HttpIdempotencyOperation(
        name: 'deleteCalendarFeedToken',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Calendar\Application\UseCase\Command\FeedToken\RevokeCalendarFeedToken\RevokeCalendarFeedTokenCommand::class,
        resultClass: \Calendar\Application\UseCase\Command\FeedToken\RevokeCalendarFeedToken\RevokeCalendarFeedTokenResult::class,
        statusCode: 204,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'tokenId',
        resourceKind: 'calendar_feed_token',
      ),
    ];
  }
  // #endregion
}
