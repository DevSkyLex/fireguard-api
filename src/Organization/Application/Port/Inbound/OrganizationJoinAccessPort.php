<?php

declare(strict_types=1);

namespace Organization\Application\Port\Inbound;

use DateTimeImmutable;
use Organization\Domain\Model\OrganizationJoin\{OrganizationDomain, OrganizationJoinRequest};

/**
 * Port OrganizationJoinAccessPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationJoinAccessPort
{
  /**
   * @since 1.0.0
   *
   * @param string $actor administrator
   * @param string $organizationId scope
   *
   * @return list<array{id:string,label:string}> non-escalating assignable roles
   */
  public function assignableRoles(string $actor, string $organizationId): array;

  /**
   * @since 1.0.0
   *
   * @param string $actor caller
   * @param string $organizationId scope
   * @param bool $settings include settings permission
   */
  public function assertManage(string $actor, string $organizationId, bool $settings = false): void;

  /**
   * @since 1.0.0
   *
   * @param string $organizationId scope
   * @param string $roleId configured role
   *
   * @return string eligible role name
   */
  public function assertEligibleRole(string $organizationId, string $roleId): string;

  /**
   * @since 1.0.0
   *
   * @param string $organizationId scope
   * @param string $email proven current address
   * @param DateTimeImmutable $now time
   *
   * @return OrganizationDomain eligible domain
   */
  public function eligibleDomain(string $organizationId, string $email, DateTimeImmutable $now): OrganizationDomain;

  /**
   * @since 1.0.0
   *
   * @param string $organizationId scope
   *
   * @return array{mode:string,roleId:?string,roleLabel:?string,domains:list<array<string,mixed>>,eligibleRoles:list<array{id:string,label:string}>} policy view
   */
  public function policyView(string $organizationId): array;

  /**
   * @since 1.0.0
   *
   * @param OrganizationDomain $domain proof
   *
   * @return array<string,mixed> domain view
   */
  public function domainView(OrganizationDomain $domain): array;

  /**
   * @since 1.0.0
   *
   * @param OrganizationJoinRequest $request request
   * @param string $actor caller
   * @param bool $manager privileged list
   *
   * @return array<string,mixed> safe request view
   */
  public function requestView(OrganizationJoinRequest $request, string $actor, bool $manager = false): array;

  /**
   * @since 1.0.0
   *
   * @param string $organizationId scope
   * @param string $roleId changed role
   * @param ?list<string> $permissions null for deletion
   */
  public function assertRoleChange(string $organizationId, string $roleId, ?array $permissions): void;
}
