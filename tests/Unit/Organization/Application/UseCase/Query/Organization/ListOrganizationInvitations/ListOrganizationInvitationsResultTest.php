<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations;

use Organization\Application\UseCase\Query\Organization\ListOrganizationInvitations\ListOrganizationInvitationsResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test ListOrganizationInvitationsResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListOrganizationInvitationsResult::class)]
final class ListOrganizationInvitationsResultTest extends TestCase
{
  #[Test]
  public function testExposesTheInvitationsItWasBuiltWith(): void
  {
    $result = new ListOrganizationInvitationsResult([]);

    self::assertSame([], $result->invitations);
    self::assertInstanceOf(ResultMessage::class, $result);
  }
}
