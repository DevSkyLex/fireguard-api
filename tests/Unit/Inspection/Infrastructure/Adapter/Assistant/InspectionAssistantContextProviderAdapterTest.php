<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Adapter\Assistant;

use Assistant\Application\Contract\Context\{AssistantContextBudget, AssistantContextScope};
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Application\Port\Outbound\NonConformityRepositoryPort;
use Inspection\Infrastructure\Adapter\Assistant\InspectionAssistantContextProviderAdapter;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test InspectionAssistantContextProviderAdapterTest.
 *
 * Covers the permission gate and the "never throws" resilience contract.
 * The DQL query itself is NEVER mocked here — it is exercised for real by
 * `tests/Integration/Inspection/Infrastructure/Adapter/Assistant/InspectionAssistantContextProviderAdapterTest`.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionAssistantContextProviderAdapter::class)]
final class InspectionAssistantContextProviderAdapterTest extends TestCase
{
  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64e01';

  private const string USER_ID = 'user-1';

  #[Test]
  public function testSupportsReturnsTrueWhenTheActorHasTheReadPermission(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.inspection.read')
      ->willReturn(true);

    $adapter = new InspectionAssistantContextProviderAdapter(
      $authorization,
      $this->createStub(NonConformityRepositoryPort::class),
      $this->createStub(EntityManagerInterface::class),
    );

    self::assertTrue($adapter->supports(self::ORG_ID, new AssistantContextScope(self::USER_ID, 'thread-1')));
  }

  #[Test]
  public function testSupportsReturnsFalseWhenTheActorLacksTheReadPermission(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $adapter = new InspectionAssistantContextProviderAdapter(
      $authorization,
      $this->createStub(NonConformityRepositoryPort::class),
      $this->createStub(EntityManagerInterface::class),
    );

    self::assertFalse($adapter->supports(self::ORG_ID, new AssistantContextScope(self::USER_ID, 'thread-1')));
  }

  #[Test]
  public function testProvideDegradesToAnEmptyFragmentWhenTheRepositoryThrows(): void
  {
    $nonConformities = $this->createStub(NonConformityRepositoryPort::class);
    $nonConformities->method('countOverviewByOrganizationId')->willThrowException(new RuntimeException('boom'));

    $adapter = new InspectionAssistantContextProviderAdapter(
      $this->createStub(OrganizationAuthorizationPort::class),
      $nonConformities,
      $this->createStub(EntityManagerInterface::class),
    );

    $fragment = $adapter->provide(self::ORG_ID, new AssistantContextScope(self::USER_ID, 'thread-1'), new AssistantContextBudget(4000));

    self::assertTrue($fragment->isEmpty());
  }
}
