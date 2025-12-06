<?php

declare(strict_types=1);

namespace Auth\Presentation\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Auth\Presentation\Http\WellKnown\JwksProvider;
use Auth\Presentation\Http\WellKnown\OpenIdConfigurationProvider;
use Auth\Presentation\Dto\Response\JwksOutput;
use Auth\Presentation\Dto\Response\OpenIdConfigurationOutput;
use Auth\Presentation\Serialization\AuthSerializationGroup;

/**
 * Resource WellKnownResource
 * @final
 *
 * @category Resource
 * @package Auth\Presentation\Resource
 * @version 1.0.0
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
