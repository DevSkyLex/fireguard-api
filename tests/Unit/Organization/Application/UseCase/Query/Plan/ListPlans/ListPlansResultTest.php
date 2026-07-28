<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Plan\ListPlans;

use Organization\Application\UseCase\Query\Plan\ListPlans\ListPlansResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;

/**
 * Test ListPlansResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListPlansResult::class)]
final class ListPlansResultTest extends TestCase
{
  #[Test]
  public function testExposesThePlansItWasBuiltWith(): void
  {
    $result = new ListPlansResult([]);

    self::assertSame([], $result->plans);
    self::assertInstanceOf(ResultMessage::class, $result);
  }
}
