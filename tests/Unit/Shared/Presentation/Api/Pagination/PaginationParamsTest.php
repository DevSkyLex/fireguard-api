<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Pagination;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Pagination\PaginationParams;

/**
 * Test PaginationParamsTest.
 *
 * @category Pagination Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PaginationParams::class)]
final class PaginationParamsTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testItExposesItsThreeComponents(): void
  {
    $params = new PaginationParams(page: 3, itemsPerPage: 20, offset: 40);

    self::assertSame(3, $params->page);
    self::assertSame(20, $params->itemsPerPage);
    self::assertSame(40, $params->offset);
  }
  // #endregion
}
