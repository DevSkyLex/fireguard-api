<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Adapter\Assistant;

use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationAssistantSettings, OrganizationId, OrganizationName, OrganizationSettings, OrganizationSlug};
use Organization\Infrastructure\Adapter\Assistant\OrganizationAssistantSettingsAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationAssistantSettingsAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationAssistantSettingsAdapter::class)]
final class OrganizationAssistantSettingsAdapterTest extends TestCase
{
  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65101';

  #[Test]
  public function testIncludeBusinessContextForReturnsTheOrganizationsFlag(): void
  {
    $organization = $this->organization(includeBusinessContext: true, enabled: true);

    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);

    $adapter = new OrganizationAssistantSettingsAdapter($repository);

    self::assertTrue($adapter->includeBusinessContextFor(self::ORG_ID));
    self::assertTrue($adapter->isEnabledFor(self::ORG_ID));
  }

  #[Test]
  public function testIncludeBusinessContextForReturnsFalseWhenTheOrganizationHasNotOptedIn(): void
  {
    $organization = $this->organization(includeBusinessContext: false, enabled: true);

    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);

    $adapter = new OrganizationAssistantSettingsAdapter($repository);

    self::assertFalse($adapter->includeBusinessContextFor(self::ORG_ID));
  }

  #[Test]
  public function testIncludeBusinessContextForFailsClosedWhenTheOrganizationIsNotFound(): void
  {
    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $adapter = new OrganizationAssistantSettingsAdapter($repository);

    self::assertFalse($adapter->includeBusinessContextFor(self::ORG_ID));
    self::assertFalse($adapter->isEnabledFor(self::ORG_ID));
  }

  #[Test]
  public function testIncludeBusinessContextForFailsClosedOnAMalformedOrganizationId(): void
  {
    $adapter = new OrganizationAssistantSettingsAdapter($this->createStub(OrganizationRepositoryPort::class));

    self::assertFalse($adapter->includeBusinessContextFor('not-a-uuid'));
  }

  private function organization(bool $includeBusinessContext, bool $enabled): Organization
  {
    return Organization::create(
      id: OrganizationId::fromString(self::ORG_ID),
      name: new OrganizationName('Fireguard Assistant Settings Org'),
      ownerUserId: '018f0b68-6758-7a12-8a1d-3f0d97f65199',
      slug: new OrganizationSlug('fireguard-assistant-settings-org'),
      settings: new OrganizationSettings(assistant: new OrganizationAssistantSettings(
        enabled: $enabled,
        includeBusinessContext: $includeBusinessContext,
      )),
    );
  }
}
