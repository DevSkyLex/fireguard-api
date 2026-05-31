# Facility Module

## Overview

Facility manages generic organizational structures such as sites, buildings,
floors, zones, and areas. It is organization-scoped and uses
`OrganizationAuthorizationPort` for permission checks.

Main goals:

- Provide a reusable, generic location hierarchy.
- Keep business-specific logic out of this module.
- Support progressive modularization (modulith today, extract later).

## API Endpoints

| Method | Path | Description |
| --- | --- | --- |
| GET | `/api/facilities/types` | List facility types for selects |
| POST | `/api/organizations/{organizationId}/facilities` | Create a facility |
| GET | `/api/organizations/{organizationId}/facilities` | List facilities (filters: `includeArchived`, `type`, `status`, `parentFacilityId`, `rootsOnly`, `code`) |
| GET | `/api/organizations/{organizationId}/facilities/{facilityId}` | Get one facility |
| GET | `/api/organizations/{organizationId}/facilities/{facilityId}/children` | List direct children for lazy tree expansion (paginated) |
| GET | `/api/organizations/{organizationId}/facilities/{facilityId}/descendants` | List all descendants for bulk subtree reads |
| PATCH | `/api/organizations/{organizationId}/facilities/{facilityId}` | Update a facility |
| POST | `/api/organizations/{organizationId}/facilities/{facilityId}/archive` | Archive a facility |
| POST | `/api/organizations/{organizationId}/facilities/{facilityId}/move` | Move a facility under another parent |

Lazy tree reads should use `/facilities?rootsOnly=true` for the initial level and
`/facilities/{facilityId}/children` when a node is expanded. The
`/descendants` endpoint is intended for bulk subtree reads and is not the
default tree table expansion contract.

## Permission Model

This module relies on Organization-scoped permissions:

- `organization.facilities.read`
- `organization.facilities.write`

## Domain Model

Aggregate:

- `Facility`

Main fields:

- `id`
- `organizationId`
- `parentFacilityId` (optional)
- `hasChildren` (read-only, indicates whether the node has visible direct children)
- `type` (`site`, `building`, `floor`, `zone`, `area`)
- `name`
- `code` (optional)
- `status` (`active`, `archived`)
- `address` (optional)
- `metadata` (JSON object)
- `createdAt`, `updatedAt`

## Persistence

- Table: `facilities` (main database)
- Doctrine mapping: `src/Facility/Infrastructure/Persistence/Doctrine/Record`
- Migration: `migrations/main/Version20260212120000.php`
- Repository: `Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityRepository`

## Architecture

- Presentation: Api Platform resources, providers, processors, DTOs.
- Application: Use cases (command/query), repository port.
- Domain: Facility aggregate, value objects, domain exceptions.
- Infrastructure: Doctrine record/mapper/repository.

## Configuration

- Service wiring: `config/modules/facility.yaml`
- Doctrine mapping (main entity manager): `config/packages/doctrine.yaml`
