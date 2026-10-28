# Troubleshooting

## Editing Permissions on Linux

If you work on Linux and cannot edit some of the project files right after
the first installation, you can run the following command
to set yourself as owner of the project files that were created by the Docker container:

```console
docker compose run --rm php chown -R $(id -u):$(id -g) .
```

## TLS/HTTPS Issues

See the [TLS section](tls.md) for more details.

## Production Issues

### How To Properly Build Fresh Images for Production Use

Remember that, by default, if you run `docker compose up --wait`,
only the files `compose.yaml` and `compose.override.yaml` will be used.
See ["How Compose works"](https://docs.docker.com/compose/intro/compose-application-model)
and ["Merge Compose files"](https://docs.docker.com/compose/how-tos/multiple-compose-files/merge).

The production image build does not require runtime secrets:

```console
docker compose -f compose.yaml -f compose.prod.yaml build --pull --no-cache
```

Production runtime settings are required separately when starting the container.
Create an untracked environment file (for example, `.env.local`) containing
`APP_SECRET`, `BASIC_AUTH_USERNAME`, `BASIC_AUTH_PASSWORD_HASH`, and
`CADDY_MERCURE_JWT_SECRET`, then pass it explicitly with `--env-file`. Compose
does not automatically load Symfony's `.env.local`. Never use throwaway values
for a production deployment or commit real credentials.

### Building Dev and Prod Images

Dev and prod images use distinct image names (`app-php-dev` and `app-php-prod`),
so they won't conflict with each other.

To build and start the dev image:

```console
docker compose up --wait
```

To build and start the prod image:

```console
docker compose -f compose.yaml -f compose.prod.yaml build --pull --no-cache
docker compose --env-file .env.local -f compose.yaml -f compose.prod.yaml -f compose.prod.runtime.yaml up --wait
```

> [!WARNING]
>
> The order of `-f` arguments matters.
