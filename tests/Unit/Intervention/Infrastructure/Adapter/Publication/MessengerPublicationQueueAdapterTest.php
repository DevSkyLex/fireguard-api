<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Infrastructure\Adapter\Publication;

use Intervention\Application\UseCase\Command\Publication\ExecutePublication\ExecutePublicationCommand;
use Intervention\Infrastructure\Adapter\Publication\MessengerPublicationQueueAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};

/**
 * Test MessengerPublicationQueueAdapterTest.
 *
 * The adapter is the seam between the publication use case and the async
 * transport: it must hand the messenger bus a command carrying the exact
 * publication identifier, since anything else silently publishes the wrong
 * record — or nothing at all.
 *
 * @category Adapter Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessengerPublicationQueueAdapter::class)]
final class MessengerPublicationQueueAdapterTest extends TestCase
{
  // #region Constants
  private const string PUBLICATION_ID = '550e8400-e29b-41d4-a716-446655472001';
  // #endregion

  // #region Methods
  #[Test]
  public function testDispatchQueuesTheExecutePublicationCommand(): void
  {
    /** @var MessageBusInterface&MockObject $messageBus */
    $messageBus = $this->createMock(MessageBusInterface::class);
    $messageBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (ExecutePublicationCommand $command): bool => self::PUBLICATION_ID === $command->publicationId))
      ->willReturn(new Envelope(new ExecutePublicationCommand(self::PUBLICATION_ID)));

    new MessengerPublicationQueueAdapter($messageBus)->dispatch(self::PUBLICATION_ID);
  }
  // #endregion
}
