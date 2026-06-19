<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use Organization\Application\Service\OrganizationNotificationPolicyService;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{
  OrganizationId,
  OrganizationName,
  OrganizationNotificationSettings,
  OrganizationSettings
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(OrganizationNotificationPolicyService::class)]
final class OrganizationNotificationPolicyServiceTest extends TestCase
{
  private const ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c12';

  #[Test]
  public function itReturnsThePersistedNotificationPolicy(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Acme'),
      ownerUserId: '018f0b68-6758-7a12-8a1d-3f0d97f63c13',
      settings: new OrganizationSettings(
        notifications: new OrganizationNotificationSettings(emailEnabled: false, interventionPublished: false),
      ),
    );

    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);

    $policy = new OrganizationNotificationPolicyService($repository)->notificationPolicy(self::ORGANIZATION_ID);

    self::assertFalse($policy->emailEnabled);
    self::assertFalse($policy->interventionPublished);
    self::assertTrue($policy->inAppEnabled);
  }

  #[Test]
  public function itFallsBackToDefaultsWhenOrganizationIsUnknown(): void
  {
    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $policy = new OrganizationNotificationPolicyService($repository)->notificationPolicy(self::ORGANIZATION_ID);

    self::assertTrue($policy->emailEnabled);
    self::assertTrue($policy->inAppEnabled);
    self::assertTrue($policy->interventionPublished);
  }

  #[Test]
  public function itFallsBackToDefaultsWhenIdentifierIsMalformed(): void
  {
    $repository = $this->createStub(OrganizationRepositoryPort::class);

    $policy = new OrganizationNotificationPolicyService($repository)->notificationPolicy('not-a-uuid');

    self::assertTrue($policy->emailEnabled);
    self::assertTrue($policy->inAppEnabled);
  }
}
