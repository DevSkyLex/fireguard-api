<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings;

use Organization\Application\Contract\Event\OrganizationSettingsUpdatedEvent;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings\{UpdateOrganizationSettingsCommand, UpdateOrganizationSettingsHandler};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(UpdateOrganizationSettingsHandler::class)]
final class UpdateOrganizationLegalProfileHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  /**
   * Method testMixedLegalIdentityAndContactPatchPreservesIndependentFields
   *
   * Verifies that an identity change cannot short-circuit the contact update and later omission preserves the populated profile.
   *
   * @access public
   *
   * @return void no return value
   */
  #[Test]
  public function testMixedLegalIdentityAndContactPatchPreservesIndependentFields(): void
  {
    $organization = $this->organization();
    $repository = $this->createMock(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);
    $repository->expects(self::exactly(3))->method('save')->with($organization);
    $transaction = $this->createMock(TransactionManagerPort::class);
    $transaction->expects(self::exactly(3))->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::exactly(2))->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof OrganizationSettingsUpdatedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && ['legal'] === $event->changedFields,
    ));
    $handler = new UpdateOrganizationSettingsHandler($repository, $transaction, $dispatcher);

    $handler(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      country: 'fr',
      legalType: 'limited_liability_company',
      legalName: 'Fireguard Paris SARL',
      registrationNumber: 'RCS PARIS 812345678',
      vatNumber: 'FR12345678901',
      registeredAddress: ['city' => ' Paris ', 'countryCode' => 'fr'],
      privacyContactEmail: ' privacy@example.com ',
    ));
    $expectedAddress = ['line1' => null, 'line2' => null, 'postalCode' => null, 'city' => 'Paris', 'region' => null, 'countryCode' => 'FR'];
    $this->assertProfile($organization, $expectedAddress, 'privacy@example.com');

    $handler(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      country: null,
      registeredAddress: null,
      privacyContactEmail: null,
    ));
    self::assertSame('FR', (string) $organization->country());
    self::assertSame('limited_liability_company', $organization->legalType()?->value);
    self::assertSame('Fireguard Paris SARL', $organization->legalName());
    self::assertSame('RCS PARIS 812345678', (string) $organization->registrationNumber());
    self::assertSame('FR12345678901', (string) $organization->vatNumber());
    $this->assertProfile($organization, $expectedAddress, 'privacy@example.com');

    $handler(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      country: '',
      privacyContactEmail: '',
    ));
    self::assertNull($organization->country());
    self::assertSame('Fireguard Paris SARL', $organization->legalName());
    self::assertSame('limited_liability_company', $organization->legalType()?->value);
    self::assertSame('RCS PARIS 812345678', (string) $organization->registrationNumber());
    self::assertSame('FR12345678901', (string) $organization->vatNumber());
    $this->assertProfile($organization, $expectedAddress, null);
  }

  #[Test]
  public function testReplacementOmissionClearAndReplayPreserveTheEventGuarantee(): void
  {
    $organization = $this->organization();
    $saved = false;
    $events = [];
    $repository = $this->createMock(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);
    $repository->expects(self::exactly(5))->method('save')->willReturnCallback(static function () use (&$saved): void {
      $saved = true;
    });
    $transaction = $this->createMock(TransactionManagerPort::class);
    $transaction->expects(self::exactly(5))->method('transactional')->willReturnCallback(static fn (callable $operation): mixed => $operation());
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::exactly(3))->method('dispatch')->willReturnCallback(static function (object $event) use (&$events, &$saved): void {
      self::assertTrue($saved, 'The event follows the durable save inside the transaction.');
      $events[] = $event;
      $saved = false;
    });
    $handler = new UpdateOrganizationSettingsHandler($repository, $transaction, $dispatcher);

    $command = new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      registeredAddress: ['line1' => '  10 rue du Test  ', 'city' => ' Paris ', 'countryCode' => 'fr'],
      privacyContactEmail: ' privacy@example.com ',
    );
    $result = $handler($command);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    $expectedAddress = ['line1' => '10 rue du Test', 'line2' => null, 'postalCode' => null, 'city' => 'Paris', 'region' => null, 'countryCode' => 'FR'];
    $this->assertProfile($organization, $expectedAddress, 'privacy@example.com');
    $updatedAt = $organization->updatedAt();

    $handler($command);
    self::assertSame($updatedAt, $organization->updatedAt(), 'Replaying normalized values is a no-op.');

    $handler(new UpdateOrganizationSettingsCommand(organizationId: self::ORGANIZATION_ID));
    $this->assertProfile($organization, $expectedAddress, 'privacy@example.com');

    $handler(new UpdateOrganizationSettingsCommand(organizationId: self::ORGANIZATION_ID, registeredAddress: ['city' => 'Lyon']));
    $this->assertProfile($organization, ['line1' => null, 'line2' => null, 'postalCode' => null, 'city' => 'Lyon', 'region' => null, 'countryCode' => null], 'privacy@example.com');

    $handler(new UpdateOrganizationSettingsCommand(organizationId: self::ORGANIZATION_ID, registeredAddress: [], privacyContactEmail: ' '));
    $this->assertProfile($organization, null, null);
    foreach ($events as $event) {
      self::assertInstanceOf(OrganizationSettingsUpdatedEvent::class, $event);
      self::assertSame(['legal'], $event->changedFields);
      self::assertSame(self::ORGANIZATION_ID, $event->organizationId);
    }
  }

  #[Test]
  #[DataProvider('invalidProfiles')]
  public function testInvalidProfileNeverSavesOrEmits(UpdateOrganizationSettingsCommand $command): void
  {
    $repository = $this->createMock(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($this->organization());
    $repository->expects(self::never())->method('save');
    $transaction = $this->createMock(TransactionManagerPort::class);
    $transaction->expects(self::never())->method('transactional');
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');
    $handler = new UpdateOrganizationSettingsHandler($repository, $transaction, $dispatcher);

    $this->expectException(InvalidValueException::class);
    $handler($command);
  }

  /**
   * @return iterable<string, array{UpdateOrganizationSettingsCommand}>
   */
  public static function invalidProfiles(): iterable
  {
    yield 'country' => [new UpdateOrganizationSettingsCommand(organizationId: self::ORGANIZATION_ID, registeredAddress: ['countryCode' => 'ZZ'])];
    yield 'email' => [new UpdateOrganizationSettingsCommand(organizationId: self::ORGANIZATION_ID, privacyContactEmail: 'invalid')];
  }

  private function organization(): Organization
  {
    return Organization::create(OrganizationId::fromString(self::ORGANIZATION_ID), new OrganizationName('Fireguard Test'), '550e8400-e29b-41d4-a716-446655440001');
  }

  /**
   * @param ?array<string, ?string> $expectedAddress
   */
  private function assertProfile(Organization $organization, ?array $expectedAddress, ?string $expectedEmail): void
  {
    self::assertSame($expectedAddress, $organization->registeredAddress()?->toArray());
    self::assertSame($expectedEmail, $organization->privacyContactEmail()?->__toString());
  }
}
