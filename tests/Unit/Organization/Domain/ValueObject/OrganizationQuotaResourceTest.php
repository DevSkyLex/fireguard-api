<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationQuotaResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// Note: OrganizationQuotaResource.php is excluded from coverage by the
// `*Resource.php` suffix rule in phpunit.dist.xml, so no #[CoversClass] target
// is declared here (it would raise a fail-on-warning coverage notice).
final class OrganizationQuotaResourceTest extends TestCase
{
  #[Test]
  public function testSummarizePhrasesACappedAllowance(): void
  {
    self::assertSame(
      'Up to 125 facilities',
      OrganizationQuotaResource::FACILITIES->summarize(125),
    );
    self::assertSame(
      'Up to 5 team members',
      OrganizationQuotaResource::MEMBERS->summarize(5),
    );
    self::assertSame(
      'Up to 10,000 equipment items',
      OrganizationQuotaResource::EQUIPMENT->summarize(10000),
    );
  }

  #[Test]
  public function testSummarizePhrasesAnUnlimitedAllowance(): void
  {
    self::assertSame(
      'Unlimited inspections',
      OrganizationQuotaResource::INSPECTIONS->summarize(null),
    );
  }
}
