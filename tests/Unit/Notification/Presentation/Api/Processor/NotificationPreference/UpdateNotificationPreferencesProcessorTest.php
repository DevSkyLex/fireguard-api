<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Presentation\Api\Processor\NotificationPreference;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Notification\Application\UseCase\Command\Notification\UpdateNotificationPreferences\{
  UpdateNotificationPreferencesCommand,
  UpdateNotificationPreferencesResult
};
use Notification\Application\UseCase\Query\Notification\GetNotificationPreferences\NotificationPreferenceResult;
use Notification\Presentation\Api\Dto\Input\NotificationPreference\{NotificationPreferenceItemInput, UpdateNotificationPreferencesInput};
use Notification\Presentation\Api\Dto\Output\NotificationPreference\NotificationPreferencesOutput;
use Notification\Presentation\Api\Processor\NotificationPreference\UpdateNotificationPreferencesProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Test UpdateNotificationPreferencesProcessorTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateNotificationPreferencesProcessor::class)]
final class UpdateNotificationPreferencesProcessorTest extends TestCase
{
  #[Test]
  public function testProcessReturnsNullForUnexpectedInput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new UpdateNotificationPreferencesProcessor(
      commandBus: $commandBus,
      security: $this->createStub(Security::class),
    );

    self::assertNull($processor->process(new stdClass(), new Patch()));
  }

  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new UpdateNotificationPreferencesProcessor(commandBus: $commandBus, security: $security);

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new UpdateNotificationPreferencesInput(), new Patch());
  }

  #[Test]
  public function testProcessDispatchesCommandAndReturnsTheUpdatedSet(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655443300'));

    $item = new NotificationPreferenceItemInput();
    $item->category = ' organization ';
    $item->emailEnabled = false;
    $item->mercureEnabled = true;

    $input = new UpdateNotificationPreferencesInput();
    $input->preferences = [$item];

    $updatedAt = new DateTimeImmutable('2026-07-18T11:00:00+00:00');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (UpdateNotificationPreferencesCommand $command): bool {
        return '550e8400-e29b-41d4-a716-446655443300' === $command->userId
          && [['category' => 'organization', 'emailEnabled' => false, 'mercureEnabled' => true]] === $command->preferences;
      }))
      ->willReturn(new UpdateNotificationPreferencesResult(preferences: [
        new NotificationPreferenceResult(category: 'organization', emailEnabled: false, mercureEnabled: true, updatedAt: $updatedAt),
      ]));

    $processor = new UpdateNotificationPreferencesProcessor(commandBus: $commandBus, security: $security);

    $output = $processor->process($input, new Patch());

    self::assertInstanceOf(NotificationPreferencesOutput::class, $output);
    self::assertCount(1, $output->preferences);
    self::assertSame('organization', $output->preferences[0]->category);
    self::assertFalse($output->preferences[0]->emailEnabled);
    self::assertTrue($output->preferences[0]->mercureEnabled);
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
