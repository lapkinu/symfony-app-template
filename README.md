# Symfony App Template

A reusable Symfony application template based on [dunglas/symfony-docker](https://github.com/dunglas/symfony-docker), with FrankenPHP, Caddy, Docker Compose, GitHub Actions, GHCR image publishing, and an immutable VPS deployment workflow.

The template separates application delivery from server provisioning. This repository builds, tests, and publishes the application, while server provisioning and runtime deployment are handled by a companion Ansible template.

## Quick Start

Requirements:

- Docker
- Docker Compose

Create a repository from this template or clone it, then run:

```console
docker compose up -d --build --wait
```

Open:

- `https://dev.localhost` — Symfony application;
- `https://landing.localhost` — public pre-launch landing page.

The local TLS certificate is generated automatically by Caddy. Your browser may require you to accept the local certificate.

Run the test suite:

```console
docker compose exec -T php bin/phpunit
```

Stop the application:

```console
docker compose down --remove-orphans
```

## Local Development

The development configuration mounts the application source into the container, so normal source changes do not require rebuilding the image.

The default local hosts are configured in `.env`:

```dotenv
MAIN_SERVER_NAME=landing.localhost
SERVER_NAME=dev.localhost
```

`landing.localhost` serves the static pre-launch site from `infrastructure/landing/`.

`dev.localhost` serves the Symfony application.

Basic Auth is disabled by the local development override.

## Application Structure

The template includes:

- Symfony;
- FrankenPHP and Caddy;
- Docker Compose development and production configurations;
- a static pre-launch landing page;
- a public `GET /health` application health endpoint;
- production Basic Auth support;
- a rootless slim production image;
- GitHub Actions CI;
- immutable production images published to GHCR.

The underlying Docker and FrankenPHP setup is based on `dunglas/symfony-docker`.

## CI/CD

GitHub Actions runs the application checks for pull requests and pushes.

For every push to `main`, CI also builds the production image and publishes it as:

```text
ghcr.io/<owner>/<repository>:<40-character-git-sha>
```

The full Git commit SHA is the production release identifier.

Production deployment is disabled by default. A repository created from this template will build and publish production images, but it will not deploy them until the following repository variable is explicitly configured:

```text
DEPLOY_ENABLED=true
```

The deployment job runs only when the event is a push to `main` and `DEPLOY_ENABLED` is exactly `true`.

See [Production Deployment](docs/production.md) for the complete deployment contract.

## Production Deployment

Production images are built by GitHub Actions and published to GitHub Container Registry. The production server does not clone the application repository or build application images locally.

Each release is identified by the full Git commit SHA and published as:

```text
ghcr.io/<owner>/<repository>:<40-character-git-sha>
```

On every push to `main`, GitHub Actions:

1. runs the application CI checks;
2. builds the production image;
3. publishes the image to GHCR using the Git commit SHA as its tag;
4. starts the production deployment job only when `DEPLOY_ENABLED=true`.

When deployment is enabled, GitHub Actions connects to the production server over SSH and invokes the server-side deployment command with the release SHA.

The server-side deployment implementation is responsible for pulling the requested immutable image, starting the release, verifying application health, and rolling back when necessary.

The application repository and server infrastructure repository are intentionally independent. The companion `ansible-vps-template` is responsible for provisioning the server-side half of this contract.

The tested production baseline is:

- Debian 13;
- amd64;
- Docker Engine;
- Docker Compose;
- GitHub Actions;
- GitHub Container Registry;
- Symfony with FrankenPHP and Caddy.

See [Production Deployment](docs/production.md) for configuration details.

## Health Checks

The template uses two separate health mechanisms.

Docker checks the internal FrankenPHP/Caddy runtime through its metrics endpoint.

The deployment system checks the Symfony application through:

```text
GET /health
```

The endpoint returns HTTP `200` with the response body:

```text
OK
```

`/health` intentionally bypasses production Basic Auth and is part of the deployment interface.

Do not remove, rename, or protect this endpoint without updating the deployment infrastructure accordingly.

## Production Configuration

Production configuration and secrets are provided at runtime. Real production secrets must not be committed to this repository or baked into the application image.

The production Compose configuration requires:

```text
APP_SECRET
BASIC_AUTH_USERNAME
BASIC_AUTH_PASSWORD_HASH
CADDY_MERCURE_JWT_SECRET
```

Missing required production values cause Compose configuration to fail before the container is started.

Local development defaults and upstream development placeholders are not production credentials.

## Pre-launch Routing

The production container can serve two hostnames with different purposes.

`MAIN_SERVER_NAME` serves the static pre-launch landing page directly from `/srv/landing`.

`SERVER_NAME` serves the Symfony application from `/app/public` and is protected by Basic Auth.

The landing page sends the following response header:

```text
X-Robots-Tag: noindex, nofollow
```

It also includes a restrictive `robots.txt` intended to prevent indexing during the pre-launch phase.

At launch, the public domain can be switched from the landing site to the Symfony application through the deployment configuration.

## Additional Documentation

The project retains useful documentation and capabilities inherited from `dunglas/symfony-docker`, including:

- [Configuration options](docs/options.md)
- [Extra services](docs/extra-services.md)
- [Xdebug](docs/xdebug.md)
- [TLS certificates](docs/tls.md)
- [MySQL](docs/mysql.md)
- [Troubleshooting](docs/troubleshooting.md)
- [AI coding agents](docs/agents.md)
- [Makefile integration](docs/makefile.md)

These documents describe optional capabilities and are not all part of the tested production deployment baseline.

## Upstream

This template is based on [dunglas/symfony-docker](https://github.com/dunglas/symfony-docker), created by Kévin Dunglas and contributors.

The upstream project provides the Symfony, Docker, FrankenPHP, Caddy, Mercure, Vulcain, development tooling, and related container foundation.

This template extends that foundation with its own application structure and production conventions, including:

- pre-launch landing routing;
- production Basic Auth;
- application health checks;
- GitHub Actions production image publishing;
- immutable Git-SHA releases;
- opt-in VPS deployment;
- a deployment contract intended for the companion Ansible template.

For upstream documentation and development, see the `dunglas/symfony-docker` project.

## License

This project retains the MIT license included with the upstream Symfony project skeleton. See [LICENSE](LICENSE).