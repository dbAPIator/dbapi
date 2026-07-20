# Changelog

All notable changes to dbAPI are documented here. Version numbers follow [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-06-12

- First public release


## [1.0.1] - 2026-06-12

- Publish docker image to Docker Hub


## [1.1.0] - 2026-06-14

- Implement access control enhancements and update documentation


## [1.2.0] - 2026-06-14

- Implement access control enhancements and update documentation


## [1.2.1] - 2026-06-15

- Release v1.2.1.


## [1.2.2] - 2026-06-15

- Update management API for single mode


## [1.2.3] - 2026-06-15

- Update management API for single mode


## [1.2.4] - 2026-06-16

- Enhance management API with single deployment mode support


## [1.2.5] - 2026-06-16

- Update Docker publish workflow to dynamically set image name


## [1.2.6] - 2026-06-16

- Enhance Docker setup for consumer projects. Refactor webhooks dispatcher in Docker setup


## [1.3.0] - 2026-06-29

- Implement sub-relation handling in API


## [1.4.0] - 2026-07-20

- Upgrade to PHP 8.3 


## [1.4.1] - 2026-07-20

- Update testing configurations and enhance error handling in DataPlane tests


## [Unreleased]

- Sparse fieldsets on `include`d resources honor `fields[{type}]` (JSON:API) and path keys `fields[{parent}/{rel}]`; outbound FK columns stay selected so relationship linkages and include hydration are not dropped when omitted from `fields`.
- CSV/XLS export uses explicit sparse fieldsets (`exportFields`) so auto-added PK/FK columns for query hydration are not exported as extra columns.
- CSV/XLS export flattens outbound (1:1) `include` relations into columns (`rel.field`); inbound (1:n) includes are skipped.
- Remove PHP resource hooks (`hooks/<entity>/before.insert.php`, etc.); use Redis webhooks for side effects.
