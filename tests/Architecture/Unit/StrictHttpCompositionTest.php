<?php

declare(strict_types=1);

namespace Tests\Architecture\Unit;

use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Tests\Support\Kernel\StrictHttpKernel;

use function json_decode;
use function strtolower;

use const JSON_THROW_ON_ERROR;

/**
 * Class StrictHttpCompositionTest
 *
 * Checks mandatory routing and browser errors through the complete real kernel without attempting a mutation.
 *
 * @category Architecture Tests
 */
final class StrictHttpCompositionTest extends KernelTestCase
{
  // #region Methods
  /**
   * Method missingOperationKeysFailAtTheMandatoryHttpBoundary
   *
   * Builds every production route before verifying the actual browser-visible login error.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function missingOperationKeysFailAtTheMandatoryHttpBoundary(): void
  {
    $kernel = self::bootKernel();
    $container = self::getContainer();
    self::assertTrue($container->getParameter('http_idempotency.required'));
    $router = $container->get('router');
    self::assertInstanceOf(RouterInterface::class, $router);
    self::assertGreaterThan(300, $router->getRouteCollection()->count());
    $response = $kernel->handle(Request::create('/api/auth/login', 'POST', server: [
      'HTTP_ORIGIN' => 'http://localhost:4200',
      'HTTP_ACCEPT' => 'application/ld+json',
      'CONTENT_TYPE' => 'application/ld+json',
    ], content: '{"email":"strict-missing-key@example.test","password":"irrelevant"}'));
    self::assertSame(400, $response->getStatusCode());
    $body = json_decode($response->getContent() ?: '', true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    self::assertSame('idempotency_key_required', $body['code'] ?? null, $response->getContent() ?: '');
    self::assertStringContainsString('idempotency-replayed', strtolower($response->headers->get('Access-Control-Expose-Headers', '')));
    self::assertStringContainsString('retry-after', strtolower($response->headers->get('Access-Control-Expose-Headers', '')));
  }

  /**
   * Method browsersReceiveRetryAfterThroughTheActualExceptionPipeline
   *
   * Injects an in-progress engine denial at the request boundary to verify the framework and CORS integration together.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function browsersReceiveRetryAfterThroughTheActualExceptionPipeline(): void
  {
    $kernel = self::bootKernel();
    $dispatcher = self::getContainer()->get('event_dispatcher');
    self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
    $dispatcher->addListener(KernelEvents::REQUEST, static function (RequestEvent $event): void {
      if ('/api/auth/login' === $event->getRequest()->getPathInfo()) {
        throw new IdempotencyDeniedException('in_progress');
      }
    }, 6);
    $response = $kernel->handle(Request::create('/api/auth/login', 'POST', server: [
      'HTTP_ORIGIN' => 'http://localhost:4200',
      'HTTP_ACCEPT' => 'application/ld+json',
      'CONTENT_TYPE' => 'application/ld+json',
      'HTTP_IDEMPOTENCY_KEY' => 'pending-logical-operation',
    ], content: '{}'));
    self::assertSame(409, $response->getStatusCode());
    self::assertSame('1', $response->headers->get('Retry-After'));
    self::assertStringContainsString('retry-after', strtolower($response->headers->get('Access-Control-Expose-Headers', '')));
    $body = json_decode($response->getContent() ?: '', true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($body);
    self::assertSame('idempotency_in_progress', $body['code'] ?? null);
  }

  /**
   * Method getKernelClass
   *
   * @access protected
   *
   * @return class-string<StrictHttpKernel> the mandatory production composition
   */
  protected static function getKernelClass(): string
  {
    return StrictHttpKernel::class;
  }
  // #endregion
}
