# Production Deployment

This document describes the production deployment contract used by this application template.

The application repository is responsible for testing the application, building the production image, publishing immutable images to GitHub Container Registry, and initiating deployment over SSH.

Server provisioning and the server-side deployment implementation are intentionally kept outside this repository. The companion `ansible-vps-template` provides that side of the deployment contract.

## Deployment Model

Every production release is identified by the full Git commit SHA.

For a push to `main`, the deployment pipeline performs the following steps:

1. GitHub Actions runs the application CI checks.
2. A production image is built from the tested commit.
3. The image is published to GitHub Container Registry as:

   ```text
   ghcr.io/<owner>/<repository>:<40-character-git-sha>
   ```

4. If production deployment is enabled, GitHub Actions connects to the configured server over SSH.
5. The workflow invokes the server-side deployment command and passes it the exact Git commit SHA.
6. The server pulls the corresponding immutable image and starts the new release.
7. The deployment implementation verifies the application through its health endpoint.
8. If the new release becomes healthy, the deployment succeeds. If deployment fails after the running release has been modified, the server-side implementation restores the previous working release.

The production server does not clone the application repository and does not build the application image. It runs the image that was already built and published by CI.

## Application and Infrastructure Contract

The application and companion infrastructure repositories use the following shared values:

```text
Application repository                    Ansible infrastructure
──────────────────────────────────────────────────────────────────
DEPLOY_APP                          <-> deployment_app_name
DEPLOY_USER                         <-> deployment_user
GHCR application repository         <-> deployment_image_repository
DEPLOY_SSH_PRIVATE_KEY              <-> deploy_ci_ssh_public_key pair
/health                             <-> deployment_healthcheck_path
```

With the default infrastructure configuration:

```text
DEPLOY_USER=deploy
```

`DEPLOY_APP` must exactly match `deployment_app_name`.

The production image published by this repository must be the same GHCR
repository configured as `deployment_image_repository` in the infrastructure
repository.

## Deployment Is Opt-in

Creating a repository from this template does not automatically enable production deployment.

For a normal push to `main`, application CI and production image publishing run automatically. The production deployment job is skipped until deployment is explicitly enabled.

Enable deployment by creating the following **repository-level Actions variable** under **Settings → Secrets and variables → Actions → Variables**:

```text
DEPLOY_ENABLED=true
```

Do not configure `DEPLOY_ENABLED` as a variable of the `production` GitHub Environment. The deployment job evaluates this repository-level variable before entering the `production` environment.

The comparison is intentionally strict. If the variable is missing or its value is anything other than the exact string `true`, the deployment job is skipped.

This makes a newly created repository fail closed: it can build and publish production artifacts without being allowed to deploy them anywhere.

## GitHub Production Environment

Create a GitHub Environment named:

```text
production
```

Configure the following environment variables:

```text
DEPLOY_HOST
DEPLOY_USER
DEPLOY_APP
```

`DEPLOY_HOST` is the production server address reachable from GitHub Actions.

`DEPLOY_USER` must match `deployment_user` in the companion infrastructure
repository. With its default configuration:

```text
DEPLOY_USER=deploy
```

`DEPLOY_APP` must exactly match `deployment_app_name` in the companion
infrastructure repository and must satisfy:

```text
[a-z0-9][a-z0-9-]*
```

For example:

```text
deployment_app_name=example-app
DEPLOY_APP=example-app
```

The deployment workflow validates that the release identifier is a full
40-character lowercase hexadecimal Git SHA.

Configure the following environment secrets:

```text
DEPLOY_SSH_PRIVATE_KEY
DEPLOY_SSH_KNOWN_HOSTS
```

### Deployment SSH private key

`DEPLOY_SSH_PRIVATE_KEY` is the private half of the dedicated CI deployment
SSH key pair.

The corresponding public key is configured in the companion Ansible repository
as `deploy_ci_ssh_public_key` and installed in the deployment user's
`authorized_keys`.

The private key must be copied into the GitHub Environment secret in its
original OpenSSH format, including the `BEGIN` and `END` lines. Do not
base64-encode it or commit it to the repository.

### SSH host verification

`DEPLOY_SSH_KNOWN_HOSTS` contains the verified public SSH host key for
`DEPLOY_HOST`.

It allows GitHub Actions to verify that it is connecting to the expected VPS.
It is not the deployment user's public key and it is not a server private key.

The companion infrastructure documentation describes how to obtain the host
key and verify its fingerprint through an already trusted administrator
connection.

After the server has been provisioned and the `production` GitHub Environment
has been fully configured, enable deployment by setting the repository-level
`DEPLOY_ENABLED` variable described above to `true`.

## SSH Contract

GitHub Actions connects using `DEPLOY_USER` and executes:

```text
sudo -n /usr/local/sbin/deploy-<DEPLOY_APP> '<40-character-sha>'
```

For example, if:

```text
DEPLOY_APP=example-app
```

the expected server-side command is:

```text
/usr/local/sbin/deploy-example-app
```

The application workflow does not require the deployment user to have unrestricted sudo access or direct Docker access.

The server provisioning layer is responsible for installing the deployment wrapper and granting only the required sudo permission.

SSH host verification is strict. `DEPLOY_SSH_KNOWN_HOSTS` must contain the verified server host key.

## Image Contract

The server-side deployment implementation must deploy the application image corresponding to the supplied Git SHA:

```text
ghcr.io/<owner>/<repository>:<sha>
```

Deployments use the exact SHA tag.

Mutable tags such as `latest` are not part of the deployment contract.

The companion infrastructure repository's `deployment_image_repository` must
refer to this same GHCR application repository.

The server runs the already-built image. Building application source on the production server is outside this deployment model.

## Runtime Configuration

Production secrets are runtime configuration and must not be baked into the image.

The production application configuration requires:

```text
APP_SECRET
BASIC_AUTH_USERNAME
BASIC_AUTH_PASSWORD_HASH
CADDY_MERCURE_JWT_SECRET
```

The production Compose configuration uses required-variable interpolation and fails if these values are missing.

`BASIC_AUTH_PASSWORD_HASH` contains the password hash consumed by Caddy Basic Auth.

The plaintext Basic Auth password is chosen when provisioning the
infrastructure and is not the same credential as the Ansible Vault password.

When a Compose environment file is generated by deployment tooling, literal `$` characters in the password hash must be preserved according to Docker Compose environment-file interpolation rules.

The companion Ansible template accepts the raw bcrypt hash and performs the
escaping required by Docker Compose. Do not manually replace `$` with `$$`
before storing that hash in Ansible Vault.

Do not commit real production values to `.env`, `.env.dev`, Compose files, or other tracked files.

Changing a runtime secret or environment value on the server does not modify
the environment of an already running container. The application container
must be recreated through the deployment mechanism before the new runtime
values take effect.

A runtime-only configuration change does not inherently require a new
application commit: an already published immutable SHA may be deployed again.
The exact same-SHA container recreation behavior is owned by the server-side
deployment implementation and should be verified before relying on this
operation for secret rotation.

## Application Hosts

The container accepts two server names:

```text
MAIN_SERVER_NAME
SERVER_NAME
```

`MAIN_SERVER_NAME` serves the static pre-launch landing page from `/srv/landing`.

`SERVER_NAME` serves the Symfony application from `/app/public`.

The application host enables Basic Auth by default in production. The local development override disables Basic Auth.

Production deployment expects the configured hostnames to resolve to the
production VPS so that HTTPS routing and deployment health validation can
operate against the intended host.

For development without production DNS, use the local
`landing.localhost` and `dev.localhost` configuration instead of treating
placeholder domains as production hostnames.

## Pre-launch Mode

The production container can serve two hostnames with different purposes.

`MAIN_SERVER_NAME` serves the static pre-launch landing page directly from `/srv/landing`.

`SERVER_NAME` serves the Symfony application from `/app/public` and is protected by Basic Auth.

A typical pre-launch configuration therefore uses the public domain as `MAIN_SERVER_NAME` and a private development domain as `SERVER_NAME`.

The landing page is served directly by Caddy and does not invoke Symfony. It sends the `X-Robots-Tag: noindex, nofollow` response header and includes a restrictive `robots.txt`.

## Launch

At launch, deployment configuration can point the public application hostname to `SERVER_NAME`.

The temporary development hostname can then be removed or disabled.

The landing page remains part of the immutable application image until the project chooses to remove it.

Changing from pre-launch to launch does not require changing the deployment protocol or image naming scheme.

## Health Checks

Two health mechanisms are intentionally used.

### Container Health

The production Docker image checks the internal FrankenPHP/Caddy metrics endpoint:

```text
http://localhost:2019/metrics
```

This verifies the container-level web runtime.

### Deployment Health

The deployment system checks the application endpoint:

```text
GET /health
```

The endpoint must return HTTP `200`.

The expected response body is:

```text
OK
```

The `/health` route intentionally bypasses Basic Auth.

This route is part of the deployment interface. Removing it, renaming it, or protecting it with Basic Auth requires a corresponding change to the server-side deployment implementation.

## Server-side Deployment Responsibilities

The server-side deployment implementation is outside this repository.

The companion infrastructure template is expected to:

1. validate the requested application and release SHA;
2. serialize deployments so that only one deployment can modify the application at a time;
3. pull the requested immutable image;
4. validate the runtime configuration;
5. start the requested release;
6. perform the application health check;
7. complete the deployment when the release is healthy.

If deployment fails after the currently running release has been modified, the server-side implementation should restore the previous working release.

The exact locking and rollback implementation is intentionally owned by the infrastructure repository rather than the application repository.

## Database Migrations

Database provisioning and database migrations are not part of the current production deployment contract.

The inherited Docker entrypoint contains optional Doctrine database readiness and migration support that becomes active when the application is configured for Doctrine.

Projects adding a database must define their own production database, migration, backup, and rollback strategy before relying on that behavior in production.

## Tested Baseline

The deployment workflow has been designed and tested with:

- Debian 13;
- amd64;
- Docker Engine;
- Docker Compose;
- GitHub Actions;
- GitHub Container Registry;
- Symfony;
- FrankenPHP;
- Caddy.

Other operating systems, CPU architectures, registries, orchestrators, and cloud providers may work, but they are not part of this template's tested production baseline.

## Companion Infrastructure Template

The intended companion repository is `ansible-vps-template`.

The application and infrastructure repositories are independent. The application template builds and publishes immutable GHCR images and initiates deployments. The infrastructure template provisions the VPS and implements the server-side deployment command.

The primary interface between them is:

```text
DEPLOY_APP               <-> deployment_app_name
DEPLOY_USER              <-> deployment_user
GHCR repository          <-> deployment_image_repository
DEPLOY_SSH_PRIVATE_KEY   <-> deploy_ci_ssh_public_key key pair
GET /health              <-> deployment_healthcheck_path
```

Keeping this interface explicit allows application development and server provisioning to evolve independently.