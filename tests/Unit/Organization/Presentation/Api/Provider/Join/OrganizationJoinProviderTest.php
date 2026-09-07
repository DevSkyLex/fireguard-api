<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Join;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Query\Join\ReadOrganizationJoin\{ReadOrganizationJoinQuery, ReadOrganizationJoinResult};
use Organization\Presentation\Api\Dto\Output\Join\OrganizationJoinOptionsOutput;
use Organization\Presentation\Api\Provider\Join\OrganizationJoinProvider;
use Organization\Presentation\Api\Service\Join\OrganizationJoinOutputAssembler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/** @category Test @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final class OrganizationJoinProviderTest extends TestCase
{
  #[Test]
  public function anonymousProviderNeverDispatchesQuery(): void
  {
    $query = $this->createMock(QueryBusPort::class);
    $query->expects(self::never())->method('ask');
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);
    $provider = new OrganizationJoinProvider($query, $security, new OrganizationJoinOutputAssembler(), $this->limiter());
    $this->expectException(AccessDeniedHttpException::class);
    $provider->provide(new Get());
  }

  #[Test]
  public function providerUsesAuthenticatedIdAndMapsExactOutput(): void
  {
    $query = $this->createMock(QueryBusPort::class);
    $query->expects(self::once())->method('ask')->with(self::callback(static fn (ReadOrganizationJoinQuery $query) => 'user' === $query->userId && 'options' === $query->operation))->willReturn(new ReadOrganizationJoinResult(['emailProofRequired' => true, 'invitations' => [], 'organizations' => [], 'requests' => []]));
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('user', 'a@corp.example', 'hash'));
    $provider = new OrganizationJoinProvider($query, $security, new OrganizationJoinOutputAssembler(), $this->limiter());
    $output = $provider->provide(new Get(output: OrganizationJoinOptionsOutput::class, extraProperties: ['join_action' => 'options']));
    self::assertInstanceOf(OrganizationJoinOptionsOutput::class, $output);
    self::assertTrue($output->emailProofRequired);
  }

  private function limiter(): RateLimiterFactory
  {
    return new RateLimiterFactory(['id' => 'join_test', 'policy' => 'fixed_window', 'limit' => 30, 'interval' => '1 minute'], new InMemoryStorage());
  }
}
