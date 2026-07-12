<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use Intervention\Application\Port\Outbound\InterventionDraftPublisherPort;
use Intervention\Application\Service\InterventionDraftPublisher;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(InterventionDraftPublisher::class)]
final class InterventionDraftPublisherTest extends TestCase
{
  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440099';

  #[Test]
  public function testPublishDelegatesToEveryPublisher(): void
  {
    $first = $this->createMock(InterventionDraftPublisherPort::class);
    $first->expects(self::once())->method('publishDrafts')->with(self::INTERVENTION_ID);
    $second = $this->createMock(InterventionDraftPublisherPort::class);
    $second->expects(self::once())->method('publishDrafts')->with(self::INTERVENTION_ID);

    new InterventionDraftPublisher([$first, $second])->publish(self::INTERVENTION_ID);
  }

  #[Test]
  public function testDiscardDelegatesToEveryPublisher(): void
  {
    $first = $this->createMock(InterventionDraftPublisherPort::class);
    $first->expects(self::once())->method('discardDrafts')->with(self::INTERVENTION_ID);
    $second = $this->createMock(InterventionDraftPublisherPort::class);
    $second->expects(self::once())->method('discardDrafts')->with(self::INTERVENTION_ID);

    new InterventionDraftPublisher([$first, $second])->discard(self::INTERVENTION_ID);
  }
}
