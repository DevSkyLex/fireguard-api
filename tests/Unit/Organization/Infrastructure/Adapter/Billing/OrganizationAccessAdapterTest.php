<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Adapter\Billing;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Adapter\Billing\OrganizationAccessAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationAccessAdapter.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationAccessAdapter::class)]
final class OrganizationAccessAdapterTest extends TestCase
{
  #[Test]
  public function testDelegatesTheDecisionToTheAuthorizationPort(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturnCallback(
      static function (string $userId, string $organizationId, string $permission) use (&$args): bool {
        $args = [$userId, $organizationId, $permission];

        return true;
      },
    );

    $adapter = new OrganizationAccessAdapter($authorization);

    self::assertTrue($adapter->hasPermission('user-1', 'org-1', 'organization.billing.read'));
    self::assertSame(['user-1', 'org-1', 'organization.billing.read'], $args);
  }

  #[Test]
  public function testPropagatesADeniedDecision(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    self::assertFalse(
      new OrganizationAccessAdapter($authorization)->hasPermission('user-1', 'org-1', 'organization.billing.read'),
    );
  }
}
