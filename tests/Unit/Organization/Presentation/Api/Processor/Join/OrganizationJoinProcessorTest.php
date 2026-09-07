<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Join;

use ApiPlatform\Metadata\Post;
use Organization\Presentation\Api\Processor\Join\OrganizationJoinProcessor;
use Organization\Presentation\Api\Service\Join\OrganizationJoinOutputAssembler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/** @category Test @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final class OrganizationJoinProcessorTest extends TestCase
{
  #[Test]
  public function anonymousMutationNeverReachesCommandBus(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);
    $limiter = new RateLimiterFactory(['id' => 'join_test', 'policy' => 'fixed_window', 'limit' => 30, 'interval' => '1 minute'], new InMemoryStorage());
    $processor = new OrganizationJoinProcessor($commands, $security, new OrganizationJoinOutputAssembler(), $limiter, $limiter);
    $this->expectException(AccessDeniedHttpException::class);
    $processor->process(null, new Post());
  }
}
