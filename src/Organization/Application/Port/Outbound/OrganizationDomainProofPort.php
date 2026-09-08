<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

/**
 * Port OrganizationDomainProofPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationDomainProofPort
{
  /**
   * @since 1.0.0
   *
   * @param string $domain input
   *
   * @return string normalized eligible business domain
   */
  public function normalize(string $domain): string;

  /**
   * @since 1.0.0
   *
   * @param string $name TXT name
   * @param string $value exact expected value
   *
   * @return ?bool result, null only for transport failure
   */
  public function verify(string $name, string $value): ?bool;

  /**
   * @since 1.0.0
   *
   * @return string unpredictable proof
   */
  public function challenge(): string;

  /**
   * @since 1.0.0
   *
   * @return string unique identifier
   */
  public function identifier(): string;
}
