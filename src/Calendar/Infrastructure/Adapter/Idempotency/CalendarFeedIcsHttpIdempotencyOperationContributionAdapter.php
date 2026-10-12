<?php

declare(strict_types=1);

namespace Calendar\Infrastructure\Adapter\Idempotency;

use Calendar\Application\UseCase\Command\Feed\ReadCalendarFeedIcs\{ReadCalendarFeedIcsCommand, ReadCalendarFeedIcsResult};
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Declares the subscription GET's hourly write and original private byte result.
 *
 * @category Adapter
 */
final readonly class CalendarFeedIcsHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  /**
   * {@inheritDoc}
   */
  public static function definitions(): array
  {
    return [new HttpIdempotencyOperation('getCalendarFeedIcs', 'main', 'root_owner', ReadCalendarFeedIcsCommand::class, ReadCalendarFeedIcsResult::class, requiredKey: true)];
  }
}
