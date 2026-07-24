<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Exception;

use Organization\Domain\Exception\OrganizationInvitationNotificationFailedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test OrganizationInvitationNotificationFailedException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationInvitationNotificationFailedException::class)]
final class OrganizationInvitationNotificationFailedExceptionTest extends TestCase
{
  #[Test]
  public function testWithIdBuildsRuntimeException(): void
  {
    $exception = OrganizationInvitationNotificationFailedException::withId('inv-123');

    self::assertInstanceOf(OrganizationInvitationNotificationFailedException::class, $exception);
    self::assertInstanceOf(RuntimeException::class, $exception);
  }

  #[Test]
  public function testWithIdEmbedsInvitationIdInMessage(): void
  {
    $exception = OrganizationInvitationNotificationFailedException::withId('inv-123');

    self::assertSame(
      'Organization invitation "inv-123" was revoked because its notification email could not be delivered.',
      $exception->getMessage(),
    );
  }

  #[Test]
  public function testWithIdHandlesEmptyIdentifier(): void
  {
    $exception = OrganizationInvitationNotificationFailedException::withId('');

    self::assertStringContainsString('Organization invitation ""', $exception->getMessage());
  }
}
