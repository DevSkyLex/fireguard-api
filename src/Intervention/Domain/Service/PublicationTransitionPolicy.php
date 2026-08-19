<?php

declare(strict_types=1);

namespace Intervention\Domain\Service;

use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Domain\ValueObject\PublicationStatus;

use function in_array;
use function sprintf;

/**
 * Service PublicationTransitionPolicy.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PublicationTransitionPolicy
{
  /**
   * Method assertAllowed.
   *
   * Executes the assert allowed operation.
   *
   * @since 1.0.0
   *
   * @param PublicationStatus $from the from value
   * @param PublicationStatus $to the to value
   */
  public function assertAllowed(PublicationStatus $from, PublicationStatus $to): void
  {
    if ($from === $to) {
      return;
    }

    if (!in_array($to, $this->allowedFrom($from), true)) {
      throw new InterventionConflictException(sprintf('Publication cannot transition from %s to %s.', $from->value, $to->value));
    }
  }

  /**
   * Method allowedFrom.
   *
   * Lists the workflow-legal next statuses from the given status.
   * `COMPLETED` is terminal.
   *
   * @since 1.0.0
   *
   * @param PublicationStatus $from the from value
   *
   * @return list<PublicationStatus> the allowed next statuses
   */
  public function allowedFrom(PublicationStatus $from): array
  {
    return match ($from) {
      PublicationStatus::PENDING => [PublicationStatus::PROCESSING, PublicationStatus::FAILED],
      PublicationStatus::PROCESSING => [PublicationStatus::COMPLETED, PublicationStatus::FAILED],
      PublicationStatus::FAILED => [PublicationStatus::PENDING],
      PublicationStatus::COMPLETED => [],
    };
  }
}
