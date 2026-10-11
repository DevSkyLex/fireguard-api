<?php

declare(strict_types=1);

namespace Tests\Integration\Shared\Infrastructure\EventDispatcher;

use Auth\Domain\Event\Session\{LoginFailedEvent, UserLoggedInEvent};
use OAuth\Domain\Event\Consent\ConsentGrantedEvent;
use Organization\Domain\Event\Organization\OrganizationSettingsUpdatedEvent;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Shared\Application\Port\Outbound\Event\{IntegrationEventConverterPort, IntegrationEventDecoderPort};
use Shared\Infrastructure\EventDispatcher\CompositeIntegrationEventDecoder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function sort;

/**
 * Class IntegrationEventDecoderWiringTest
 *
 * Checks module converter registration in the compiled application container.
 *
 * @category Integration Test
 */
final class IntegrationEventDecoderWiringTest extends KernelTestCase
{
  // #region Constants
  /**
   * @var list<string> owners publishing supported historical snapshots
   */
  private const array OWNERS = [
    'Approval', 'Assistant', 'Audit', 'Auth', 'Automation', 'Calendar', 'Compliance', 'Customer',
    'Equipment', 'Facility', 'Import', 'Inspection', 'Intervention', 'Maintenance', 'MaintenanceExport',
    'Messaging', 'OAuth', 'Onboarding', 'Organization', 'Otp', 'Procurement', 'ServiceRequest', 'User', 'Webhook',
  ];
  // #endregion

  // #region Tests
  #[Test]
  public function compiledContainerRegistersEveryOwnerConverter(): void
  {
    self::bootKernel();
    $decoder = self::getContainer()->get(IntegrationEventDecoderPort::class);
    self::assertInstanceOf(CompositeIntegrationEventDecoder::class, $decoder);
    $converters = new ReflectionClass($decoder)->getProperty('converters')->getValue($decoder);
    self::assertIsIterable($converters);
    $actual = [];
    foreach ($converters as $converter) {
      self::assertInstanceOf(IntegrationEventConverterPort::class, $converter);
      $actual[] = $converter::class;
    }
    $expected = [];
    foreach (self::OWNERS as $owner) {
      $expected[] = $owner . '\\Infrastructure\\Adapter\\Event\\IntegrationEventConverterAdapter';
    }
    sort($actual);
    sort($expected);

    self::assertSame($expected, $actual);
  }

  #[Test]
  public function compiledDecoderConvertsOwnersWithoutInfrastructureResourceDiscovery(): void
  {
    self::bootKernel();
    $decoder = self::getContainer()->get(IntegrationEventDecoderPort::class);
    self::assertInstanceOf(IntegrationEventDecoderPort::class, $decoder);
    $login = new UserLoggedInEvent('user-123', 'user@example.com', '127.0.0.1');
    $failure = new LoginFailedEvent('user@example.com', '127.0.0.1', 'invalid_credentials');
    $consent = new ConsentGrantedEvent('user-123', 'client-123', ['openid'], true);
    $settings = new OrganizationSettingsUpdatedEvent('organization-123', ['compliance']);

    $publicLogin = $decoder->decode($login);
    self::assertInstanceOf(\Auth\Application\Contract\Event\Session\UserLoggedInEvent::class, $publicLogin);
    self::assertSame($login->occurredAt, $publicLogin->occurredAt);
    $publicFailure = $decoder->decode($failure);
    self::assertInstanceOf(\Auth\Application\Contract\Event\Session\LoginFailedEvent::class, $publicFailure);
    self::assertSame($failure->occurredAt, $publicFailure->occurredAt);
    self::assertInstanceOf(\OAuth\Application\Contract\Event\Consent\ConsentGrantedEvent::class, $decoder->decode($consent));
    self::assertInstanceOf(\Organization\Application\Contract\Event\OrganizationSettingsUpdatedEvent::class, $decoder->decode($settings));
  }
  // #endregion
}
