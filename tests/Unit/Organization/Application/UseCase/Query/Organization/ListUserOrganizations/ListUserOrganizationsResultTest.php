<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\ListUserOrganizations;

use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\ListUserOrganizationsResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test ListUserOrganizationsResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListUserOrganizationsResult::class)]
final class ListUserOrganizationsResultTest extends TestCase
{
  #[Test]
  public function testExposesTheOrganizationsItWasBuiltWith(): void
  {
    $result = new ListUserOrganizationsResult([]);

    self::assertSame([], $result->organizations);
    self::assertInstanceOf(ResultMessage::class, $result);
  }
}
