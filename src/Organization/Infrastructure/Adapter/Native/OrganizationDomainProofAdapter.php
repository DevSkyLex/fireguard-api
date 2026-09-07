<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Adapter\Native;

use Organization\Application\Port\Outbound\OrganizationDomainProofPort;
use Organization\Domain\Exception\OrganizationJoinInputException;

use function array_slice;
use function bin2hex;
use function chr;
use function count;
use function dns_get_record;
use function explode;
use function file;
use function function_exists;
use function hash_equals;
use function idn_to_ascii;
use function implode;
use function is_file;
use function is_string;
use function ord;
use function preg_match;
use function random_bytes;
use function rtrim;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

use const DNS_TXT;
use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;

/**
 * Adapter OrganizationDomainProofAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationDomainProofAdapter implements OrganizationDomainProofPort
{
  private const array GENERIC = ['gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'hotmail.fr', 'outlook.fr', 'live.com', 'live.fr', 'msn.com', 'yahoo.com', 'yahoo.fr', 'ymail.com', 'icloud.com', 'me.com', 'mac.com', 'aol.com', 'proton.me', 'protonmail.com', 'pm.me', 'gmx.com', 'gmx.de', 'gmx.fr', 'mail.com', 'zoho.com', 'orange.fr', 'wanadoo.fr', 'laposte.net', 'free.fr', 'sfr.fr', 'bbox.fr', 'tuta.com', 'tutanota.com', 'yandex.com', 'yandex.ru', 'qq.com', '163.com'];

  /**
   * @since 1.0.0
   *
   * @param string $domain submitted domain
   *
   * @return string validated ASCII domain
   */
  public function normalize(string $domain): string
  {
    $domain = strtolower(rtrim(trim($domain), '.'));
    if (function_exists('idn_to_ascii')) {
      $ascii = idn_to_ascii($domain);
      if (false === $ascii) {
        throw new OrganizationJoinInputException('organization_join_domain_invalid');
      }
      $domain = strtolower($ascii);
    }
    if (1 !== preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $domain)) {
      throw new OrganizationJoinInputException('organization_join_domain_invalid');
    }
    $psl = __DIR__ . '/../../Resources/public_suffix_list.dat';
    $disposable = __DIR__ . '/../../Resources/disposable_email_blocklist.conf';
    if (!is_file($psl) || !is_file($disposable)) {
      throw new OrganizationJoinInputException('organization_join_domain_catalog_unavailable');
    }
    $rules = file($psl, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $blocked = file($disposable, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (false === $rules || false === $blocked) {
      throw new OrganizationJoinInputException('organization_join_domain_catalog_unavailable');
    }
    $parts = explode('.', $domain);
    $suffix = $parts[count($parts) - 1];
    $exception = false;
    foreach ($rules as $rule) {
      $rule = strtolower(trim($rule));
      if ('' === $rule || str_starts_with($rule, '//')) {
        continue;
      }
      if ('!' . $domain === $rule) {
        $exception = true;
      }
      if ($domain === $rule || ('*.' . implode('.', array_slice($parts, 1))) === $rule) {
        $suffix = $domain;
      }
    }
    if (!$exception && $suffix === $domain) {
      throw new OrganizationJoinInputException('organization_join_domain_public');
    }
    foreach ([...self::GENERIC, ...$blocked] as $item) {
      $item = strtolower(trim($item));
      if ('' !== $item && ($domain === $item || str_ends_with($domain, '.' . $item))) {
        throw new OrganizationJoinInputException('organization_join_domain_generic');
      }
    }

    return $domain;
  }

  /**
   * @since 1.0.0
   *
   * @param string $name record name
   * @param string $value expected proof
   *
   * @return ?bool DNS result
   */
  public function verify(string $name, string $value): ?bool
  {
    // false means resolver/transport failure; an empty authoritative answer is absence.
    $records = @dns_get_record($name, DNS_TXT);
    if (false === $records) {
      return null;
    }
    foreach ($records as $record) {
      $text = $record['txt'] ?? null;
      if (!is_string($text)) {
        continue;
      }
      if (hash_equals($value, $text)) {
        return true;
      }
    }

    return false;
  }

  /**
   * @since 1.0.0
   *
   * @return string public random TXT proof
   */
  public function challenge(): string
  {
    return 'fireguard-verification=' . bin2hex(random_bytes(32));
  }

  /**
   * @since 1.0.0
   *
   * @return string RFC 4122 random identifier
   */
  public function identifier(): string
  {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
    $hex = bin2hex($bytes);

    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
  }
}
