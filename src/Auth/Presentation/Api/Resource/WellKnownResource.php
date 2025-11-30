<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Resource;

use ApiPlatform\Metadata\{
  ApiResource,
  Get
};
use Auth\Presentation\Api\Provider\{
  JwksProvider,
  OpenIdConfigurationProvider
};
use Auth\Presentation\Api\Dto\{
  JwksOutput,
  OpenIdConfigurationOutput
};
use Auth\Presentation\Api\Serialization\AuthSerializationGroup;

/**
 * Resource WellKnownResource
 * @final
 *
 * API Platform resource for .well-known endpoints.
 * Implements OpenID Connect Discovery 1.0.
 *
 * @category Resource
 * @package Auth\Presentation\Api\Resource
 * @version 1.0.0
 *
 * @see https://openid.net/specs/openid-connect-discovery-1_0.html
 * @see https://datatracker.ietf.org/doc/html/rfc7517
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[ApiResource(
  shortName: 'WellKnown',
  routePrefix: '/.well-known',
  description: 'OpenID Connect Discovery endpoints',
  operations: [
    new Get(
      name: 'openid_configuration',
      description: 'OpenID Connect Discovery (RFC 8414)',
      uriTemplate: '/openid-configuration',
      input: false,
      output: OpenIdConfigurationOutput::class,
      provider: OpenIdConfigurationProvider::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::READ]]
    ),
    new Get(
      name: 'jwks',
      description: 'JSON Web Key Set (RFC 7517)',
      uriTemplate: '/jwks.json',
      input: false,
      output: JwksOutput::class,
      provider: JwksProvider::class,
      normalizationContext: ['groups' => [AuthSerializationGroup::READ]]
    )
  ]
)]
final class WellKnownResource
{
}
