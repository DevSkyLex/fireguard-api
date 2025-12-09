<?php

declare(strict_types=1);

namespace Auth\Presentation\Serialization;

/**
 * Class AuthSerializationGroup
 * @final
 *
 * Serialization groups for Auth module.
 *
 * @category Serialization
 * @package Auth\Presentation\Serialization
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthSerializationGroup
{
  public const string READ = 'token:read';
  public const string WRITE = 'token:write';
  public const string CONSENT_READ = 'consent:read';
}
