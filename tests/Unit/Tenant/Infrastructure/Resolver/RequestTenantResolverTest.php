<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Infrastructure\Resolver;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Tenant\Infrastructure\Resolver\RequestTenantResolver;

/**
 * Test RequestTenantResolverTest.
 *
 * @category Resolver Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RequestTenantResolver::class)]
final class RequestTenantResolverTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testResolveTenantIdReturnsNullWhenNoRequest(): void
  {
    $resolver = new RequestTenantResolver(new RequestStack());

    self::assertNull($resolver->resolveTenantId());
  }

  #[Test]
  public function testResolveTenantIdFromHeader(): void
  {
    $request = new Request();
    $request->headers->set('X-Tenant-Id', '123e4567-e89b-12d3-a456-426614174000');

    $stack = new RequestStack();
    $stack->push($request);

    $resolver = new RequestTenantResolver($stack);

    self::assertSame('123e4567-e89b-12d3-a456-426614174000', $resolver->resolveTenantId());
  }

  #[Test]
  public function testResolveTenantIdReturnsNullForInvalidUuid(): void
  {
    $request = new Request();
    $request->headers->set('X-Tenant-Id', 'invalid-uuid');

    $stack = new RequestStack();
    $stack->push($request);

    $resolver = new RequestTenantResolver($stack);

    self::assertNull($resolver->resolveTenantId());
  }

  #[Test]
  public function testResolveTenantIdReturnsNullForEmptyString(): void
  {
    $request = new Request();
    $request->headers->set('X-Tenant-Id', '');

    $stack = new RequestStack();
    $stack->push($request);

    $resolver = new RequestTenantResolver($stack);

    self::assertNull($resolver->resolveTenantId());
  }
  // #endregion
}
