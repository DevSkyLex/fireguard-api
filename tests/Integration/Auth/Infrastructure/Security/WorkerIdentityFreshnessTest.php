<?php

declare(strict_types=1);

namespace Tests\Integration\Auth\Infrastructure\Security;

use Auth\Infrastructure\Security\User\SecurityUserProvider;
use Authorization\Application\Port\Inbound\AuthorizationPort;
use Authorization\Application\Port\Outbound\Internal\{RoleAssignmentRepositoryPort, RoleRepositoryPort};
use Authorization\Domain\Model\RoleAssignment\RoleAssignment;
use Authorization\Domain\ValueObject\{RoleAssignmentId, RoleName};
use Session\Application\Port\Inbound\SessionRevocationPort;
use Session\Application\Port\Inbound\Tracking\{SessionStatusPort, SessionTrackingPort};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Helper\TestEventIdProvider;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\Internal\UserRepositoryPort;

/**
 * Reuses the real kernel's identity services across worker-style boundaries.
 *
 * @category Integration Test
 */
final class WorkerIdentityFreshnessTest extends KernelTestCase
{
  public function testRevocationAccountAndRoleChangesDoNotLeakAcrossMessages(): void
  {
    self::bootKernel();
    $container = self::getContainer();
    $users = $container->get(UserRepositoryPort::class);
    $assignments = $container->get(RoleAssignmentRepositoryPort::class);
    $roles = $container->get(RoleRepositoryPort::class);
    $authorization = $container->get(AuthorizationPort::class);
    $provider = $container->get(SecurityUserProvider::class);
    self::assertInstanceOf(UserRepositoryPort::class, $users);
    self::assertInstanceOf(RoleAssignmentRepositoryPort::class, $assignments);
    self::assertInstanceOf(RoleRepositoryPort::class, $roles);
    self::assertInstanceOf(AuthorizationPort::class, $authorization);
    self::assertInstanceOf(SecurityUserProvider::class, $provider);
    $id = 'b7d22001-3a71-4c7a-9f00-000000000001';
    $user = UserTestFactory::createActive(id: $id, email: 'worker-freshness@example.test', username: 'worker_freshness');
    $users->save($user);
    $role = $roles->findByName(new RoleName('admin'));
    self::assertNotNull($role);
    $assignment = RoleAssignment::assignToUser(new RoleAssignmentId('b7d22001-3a71-4c7a-9f00-000000000002'), $role->id(), $id);
    $assignments->save($assignment);
    self::assertContains('admin', $authorization->getUserRoleNames($id));
    self::assertContains('ROLE_ADMIN', $provider->loadUserById($id, ['read'])->getRoles());
    self::assertSame(['read'], $provider->loadUserById($id, ['read'])->getScopes());

    $tracking = $container->get(SessionTrackingPort::class);
    $status = $container->get(SessionStatusPort::class);
    $revocation = $container->get(SessionRevocationPort::class);
    self::assertInstanceOf(SessionTrackingPort::class, $tracking);
    self::assertInstanceOf(SessionStatusPort::class, $status);
    self::assertInstanceOf(SessionRevocationPort::class, $revocation);
    $tracking->recordSession($id, '127.0.0.1', 'worker-test', 'worker-access-token', 'worker-refresh-token', false);
    self::assertNotNull($status->activeSessionId('worker-access-token', $id));
    $assignments->delete($assignment);
    $revocation->revokeAllForUser($id);
    $user->deactivate(new TestEventIdProvider());
    $users->save($user);
    $resetter = $container->get('services_resetter');
    self::assertInstanceOf(\Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter::class, $resetter);
    $resetter->reset();

    self::assertNotContains('admin', $authorization->getUserRoleNames($id));
    self::assertNull($status->activeSessionId('worker-access-token', $id));
    $principal = $provider->loadUserById($id, ['profile']);
    self::assertFalse($principal->isActive());
    self::assertNotContains('ROLE_ADMIN', $principal->getRoles());
    self::assertSame(['profile'], $principal->getScopes());
  }
}
