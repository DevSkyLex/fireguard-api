<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ListOrganizationMembers;

use Organization\Application\UseCase\Query\Organization\ListOrganizationMembers\ListOrganizationMembersResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test ListOrganizationMembersResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListOrganizationMembersResult::class)]
final class ListOrganizationMembersResultTest extends TestCase
{
  #[Test]
  public function testExposesTheMembersItWasBuiltWith(): void
  {
    $result = new ListOrganizationMembersResult([]);

    self::assertSame([], $result->members);
    self::assertInstanceOf(ResultMessage::class, $result);
  }
}
