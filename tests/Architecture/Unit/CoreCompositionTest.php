<?php

declare(strict_types=1);

namespace Tests\Architecture\Unit;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Shared\Infrastructure\EventDispatcher\CompositeIntegrationEventDecoder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Tests\Support\Kernel\CoreKernel;

use function array_keys;
use function array_unique;
use function array_values;
use function explode;
use function in_array;
use function sort;
use function str_contains;
use function str_starts_with;
use function strtolower;

/**
 * Class CoreCompositionTest
 *
 * Proves the selected modules boot without business services, resources or worker queues.
 * Database assertions use full migrated template clones rather than an invented core schema.
 *
 * @category Architecture Tests
 */
final class CoreCompositionTest extends KernelTestCase
{
  /**
   * Method selectedModuleServicesAndResourcesBootWithoutBusinessModules
   *
   * Inspects the actual compiled container and route metadata, including absent modules.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function selectedModuleServicesAndResourcesBootWithoutBusinessModules(): void
  {
    self::bootKernel();
    $container = self::getContainer();
    self::assertInstanceOf(CompositeIntegrationEventDecoder::class, $container->get(CompositeIntegrationEventDecoder::class));

    foreach ($container->getServiceIds() as $serviceId) {
      foreach (['Organization', 'Facility', 'Equipment', 'Inspection', 'Intervention', 'Billing', 'Maintenance', 'Automation', 'Messaging', 'Import', 'Compliance', 'Webhook', 'Approval', 'Assistant', 'Calendar', 'Workload', 'Customer', 'ServiceRequest', 'Inventory', 'Procurement', 'MaintenanceCost', 'MaintenanceExport', 'Onboarding'] as $module) {
        self::assertFalse(str_starts_with($serviceId, $module . '\\'), 'Unselected service: ' . $serviceId);
      }
    }

    /** @var RouterInterface $router */
    $router = $container->get('router');
    $healthFound = false;
    foreach ($router->getRouteCollection() as $route) {
      $resource = $route->getDefault('_api_resource_class');
      if (null !== $resource) {
        self::assertIsString($resource);
        if (!str_starts_with($resource, 'ApiPlatform\\')) {
          self::assertContains(explode('\\', $resource)[0], CoreKernel::MODULES, 'Unselected API resource: ' . $resource);
        }
      }
      self::assertFalse(str_contains($route->getPath(), '/billing/'));
      $healthFound = $healthFound || '/api/health' === $route->getPath();
    }
    self::assertTrue($healthFound);

    $receiverLocator = $container->get('messenger.receiver_locator');
    self::assertInstanceOf(ServiceProviderInterface::class, $receiverLocator);
    $receivers = $receiverLocator->getProvidedServices();
    foreach (array_keys($receivers) as $receiver) {
      self::assertFalse(in_array($receiver, ['webhook', 'assistant'], true));
      self::assertFalse(str_starts_with($receiver, 'scheduler_'), 'Unselected scheduler: ' . $receiver);
    }
  }

  /**
   * Method entityManagersKeepExactCoreOwnershipOnExistingDatabaseClones
   *
   * Checks metadata selection independently of the migrated tables retained in test clones.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function entityManagersKeepExactCoreOwnershipOnExistingDatabaseClones(): void
  {
    self::bootKernel();
    foreach (['auth' => ['Audit', 'Auth', 'Authorization', 'OAuth', 'Otp', 'Session', 'Shared', 'Tenant', 'TrustedDevice', 'User'], 'main' => ['Notification', 'Shared']] as $name => $expected) {
      /** @var EntityManagerInterface $manager */
      $manager = self::getContainer()->get('doctrine.orm.' . $name . '_entity_manager');
      $owners = [];
      foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
        $owners[] = explode('\\', $metadata->name)[0];
      }
      $owners = array_values(array_unique($owners));
      sort($owners);
      sort($expected);
      self::assertSame($expected, $owners);
      $database = $manager->getConnection()->fetchOne('SELECT current_database()');
      self::assertIsString($database);
      self::assertMatchesRegularExpression('/_test_w[A-Za-z0-9_]+$/', $database);
    }
    /** @var EntityManagerInterface $main */
    $main = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertSame('organizations', $main->getConnection()->fetchOne("SELECT to_regclass('public.organizations')"));
    self::assertTrue($main->getMetadataFactory()->isTransient('Organization\\Infrastructure\\Persistence\\Doctrine\\Record\\OrganizationRecord'));
  }

  /**
   * Method protectedCoreRoutesStillRejectUnauthenticatedRequests
   *
   * Protects the authentication denial path when only reusable modules are loaded.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function protectedCoreRoutesStillRejectUnauthenticatedRequests(): void
  {
    $kernel = self::bootKernel();
    $response = $kernel->handle(Request::create('/api/users', 'GET', server: ['HTTP_ACCEPT' => 'application/ld+json']));
    self::assertSame(401, $response->getStatusCode());
  }

  /**
   * Method browsersCanSendKeysAndObserveReplayHeaders
   *
   * Checks real CORS preflight and response headers so browser clients can use the protocol.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function browsersCanSendKeysAndObserveReplayHeaders(): void
  {
    $kernel = self::bootKernel();
    $response = $kernel->handle(Request::create('/api/auth/login', 'OPTIONS', server: [
      'HTTP_ORIGIN' => 'http://localhost:4200',
      'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
      'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type,Idempotency-Key',
    ]));
    self::assertSame(200, $response->getStatusCode());
    self::assertStringContainsString('idempotency-key', strtolower($response->headers->get('Access-Control-Allow-Headers', '')));

    $response = $kernel->handle(Request::create('/api/users', 'GET', server: [
      'HTTP_ORIGIN' => 'http://localhost:4200',
      'HTTP_ACCEPT' => 'application/ld+json',
    ]));
    self::assertSame(401, $response->getStatusCode());
    self::assertStringContainsString('idempotency-replayed', strtolower($response->headers->get('Access-Control-Expose-Headers', '')));
    self::assertStringContainsString('retry-after', strtolower($response->headers->get('Access-Control-Expose-Headers', '')));
  }

  // #region Methods
  /**
   * Method getKernelClass
   *
   * @access protected
   *
   * @return class-string<CoreKernel> the explicit test composition
   */
  protected static function getKernelClass(): string
  {
    return CoreKernel::class;
  }
  // #endregion
}
