#!/usr/bin/env bash

set -euo pipefail

APP_NAME="${1:-}"

if [[ -z "$APP_NAME" ]]; then
  echo "Usage: bash NEW/setup.sh <app-name>"
  exit 1
fi

if [[ ! "$APP_NAME" =~ ^[a-z][a-z0-9-]*$ ]]; then
  echo "App name must start with a lowercase letter and use lowercase letters, numbers, and hyphens only."
  exit 1
fi

for bin in git php composer pnpm; do
  if ! command -v "$bin" >/dev/null 2>&1; then
    echo "Missing required command: $bin"
    exit 1
  fi
done

if [[ -e "$APP_NAME" ]]; then
  echo "Target path already exists: $APP_NAME"
  exit 1
fi

PHP_NAMESPACE="$(echo "$APP_NAME" | sed -E 's/(^|-)([a-z0-9])/\U\2/g')"
DB_NAME="$(echo "$APP_NAME" | tr '-' '_')"

echo "Creating Atomy-Q stack scaffold: $APP_NAME"

mkdir "$APP_NAME"
cd "$APP_NAME"

git init

mkdir -p apps packages

# ----------------------------
# PNPM + TURBOREPO WORKSPACE
# ----------------------------

cat <<EOF > pnpm-workspace.yaml
packages:
  - "apps/*"
  - "packages/*"
EOF

cat <<EOF > package.json
{
  "name": "$APP_NAME",
  "private": true,
  "packageManager": "pnpm@9.15.0",
  "scripts": {
    "dev": "turbo run dev --parallel",
    "dev:web": "pnpm --filter web dev",
    "dev:api": "pnpm --filter api dev",
    "docker:up": "bash scripts/docker-compose.sh up -d",
    "docker:down": "bash scripts/docker-compose.sh down",
    "docker:logs": "bash scripts/docker-compose.sh logs -f",
    "docker:tools": "bash scripts/docker-compose.sh --profile tools up -d",
    "setup": "cp -n .env.docker.example .env.docker && pnpm docker:up && pnpm install && cd apps/api && cp -n .env.example .env && composer install && php artisan key:generate --force && php artisan migrate",
    "build": "turbo run build",
    "lint": "turbo run lint",
    "format": "prettier --write .",
    "test": "turbo run test",
    "test:api": "pnpm --filter api test",
    "test:web": "pnpm --filter web test",
    "test:e2e": "pnpm --filter web test:e2e",
    "typecheck": "turbo run typecheck",
    "check:workspace": "bash scripts/check-workspace.sh",
    "api:docs": "pnpm --filter api openapi",
    "generate:api": "pnpm api:docs && pnpm --filter @atomy-q/api-client generate",
    "prepare": "husky"
  },
  "devDependencies": {
    "husky": "^9.0.0",
    "lint-staged": "^15.0.0",
    "orval": "^7.0.0",
    "prettier": "^3.0.0",
    "turbo": "^2.0.0",
    "typescript": "^5.0.0"
  },
  "lint-staged": {
    "*.{js,jsx,ts,tsx,json,md,css}": "prettier --write",
    "apps/api/**/*.php": "apps/api/vendor/bin/pint"
  }
}
EOF

cat <<EOF > turbo.json
{
  "\$schema": "https://turbo.build/schema.json",
  "ui": "tui",
  "tasks": {
    "dev": {
      "cache": false,
      "persistent": true
    },
    "build": {
      "dependsOn": ["^build"],
      "inputs": ["\$TURBO_DEFAULT\$", ".env*"],
      "outputs": [".next/**", "!.next/cache/**", "dist/**"]
    },
    "lint": {
      "dependsOn": ["^lint"]
    },
    "typecheck": {
      "dependsOn": ["^typecheck"]
    },
    "test": {
      "dependsOn": ["^test"]
    },
    "generate": {
      "cache": false
    },
    "openapi": {
      "cache": false
    }
  }
}
EOF

cat <<EOF > .gitignore
node_modules
vendor
.env
.env.*
!.env.example
!.env.docker.example
.next
dist
build
coverage
storage/logs/*.log
EOF

# ----------------------------
# WORKSPACE CONTRACT
# ----------------------------

mkdir -p docs/decisions scripts .vscode .husky packages/config packages/types

cat <<'EOF' > .editorconfig
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
indent_style = space
indent_size = 2
trim_trailing_whitespace = true

[*.php]
indent_size = 4

[Makefile]
indent_style = tab
EOF

cat <<'EOF' > .vscode/extensions.json
{
  "recommendations": [
    "bmewburn.vscode-intelephense-client",
    "bradlc.vscode-tailwindcss",
    "dbaeumer.vscode-eslint",
    "esbenp.prettier-vscode",
    "xdebug.php-debug"
  ]
}
EOF

cat <<'EOF' > .vscode/settings.json
{
  "editor.defaultFormatter": "esbenp.prettier-vscode",
  "editor.formatOnSave": true,
  "files.eol": "\n",
  "tailwindCSS.experimental.classRegex": [
    ["cn\\(([^)]*)\\)", "[\"'`]([^\"'`]*).*?[\"'`]"]
  ]
}
EOF

cat <<'EOF' > .husky/pre-commit
#!/usr/bin/env sh

pnpm lint-staged
EOF

chmod +x .husky/pre-commit

cat <<'EOF' > Makefile
.PHONY: setup docker-up docker-down docker-tools api-migrate api-test web-test test build lint typecheck generate-api health

setup:
	pnpm setup

docker-up:
	pnpm docker:up

docker-down:
	pnpm docker:down

docker-tools:
	pnpm docker:tools

api-migrate:
	cd apps/api && php artisan migrate

api-test:
	pnpm test:api

web-test:
	pnpm test:web

test:
	pnpm test

build:
	pnpm build

lint:
	pnpm lint

typecheck:
	pnpm typecheck

generate-api:
	pnpm generate:api

health:
	pnpm check:workspace
EOF

cat <<'EOF' > scripts/check-workspace.sh
#!/usr/bin/env bash

set -euo pipefail

failures=0

check_command() {
  if command -v "$1" >/dev/null 2>&1; then
    printf 'ok: %s\n' "$1"
  else
    printf 'missing: %s\n' "$1"
    failures=$((failures + 1))
  fi
}

check_file() {
  if [[ -f "$1" ]]; then
    printf 'ok: %s\n' "$1"
  else
    printf 'missing: %s\n' "$1"
    failures=$((failures + 1))
  fi
}

check_dir() {
  if [[ -d "$1" ]]; then
    printf 'ok: %s\n' "$1"
  else
    printf 'missing: %s\n' "$1"
    failures=$((failures + 1))
  fi
}

check_command docker
check_command php
check_command composer
check_command node
check_command pnpm

check_file .env.docker
check_file docker-compose.yml
check_file apps/api/.env
check_file apps/api/composer.json
check_file apps/web/package.json
check_file packages/api-client/package.json
check_file orval.config.ts

check_dir apps/api/Modules
check_dir apps/web/src/features
check_dir packages/api-client/src/generated

if bash scripts/docker-compose.sh config >/dev/null 2>&1; then
  printf 'ok: docker compose config\n'
else
  printf 'failed: docker compose config\n'
  failures=$((failures + 1))
fi

if [[ "$failures" -gt 0 ]]; then
  printf '\nworkspace check failed: %s issue(s)\n' "$failures"
  exit 1
fi

printf '\nworkspace check passed\n'
EOF

chmod +x scripts/check-workspace.sh

cat <<'EOF' > scripts/docker-compose.sh
#!/usr/bin/env bash

set -euo pipefail

if docker compose version >/dev/null 2>&1; then
  exec docker compose --env-file .env.docker "$@"
fi

if command -v docker-compose >/dev/null 2>&1; then
  exec docker-compose --env-file .env.docker "$@"
fi

cat >&2 <<'MSG'
Docker Compose is not available.

Install the Docker Compose v2 plugin (`docker compose`) or the legacy
`docker-compose` binary, then rerun the command.
MSG

exit 127
EOF

chmod +x scripts/docker-compose.sh

cat <<EOF > README.md
# $APP_NAME

$APP_NAME is an Atomy-Q monorepo scaffold with a Laravel API, Next.js frontend,
shared Orval-generated API client, shadcn/ui primitives, and local Docker
services.

## Workspace Layout

| Path | Purpose |
| --- | --- |
| \`apps/api\` | Laravel API grouped by domain modules |
| \`apps/web\` | Next.js app using shadcn/ui primitives |
| \`packages/api-client\` | Orval-generated shared TypeScript client and types |
| \`packages/config\` | Shared config conventions and future package presets |
| \`packages/types\` | Hand-authored cross-app TypeScript types, only when not generated |
| \`docs\` | Architecture notes, decisions, and operating guidance |

## First Run

\`\`\`bash
pnpm setup
pnpm dev
\`\`\`

## Common Commands

\`\`\`bash
pnpm docker:up
pnpm docker:tools
pnpm generate:api
pnpm lint
pnpm typecheck
pnpm test
pnpm build
pnpm check:workspace
\`\`\`

## Contracts

- Backend domains live in Laravel Modules under \`apps/api/Modules\`.
- Frontend product code is grouped by feature under \`apps/web/src/features\`.
- shadcn-owned primitives live in \`apps/web/src/components/ui\`.
- Atomy-Q composites live outside \`components/ui\`, usually in
  \`components/domain\`, \`components/layout\`, or a feature folder.
- Frontend/backend contracts flow through OpenAPI and \`packages/api-client\`.
- Generated API client changes must be committed with the backend contract
  change that caused them.
EOF

cat <<'EOF' > CONTRIBUTING.md
# Contributing

## Development Flow

1. Start local services with `pnpm docker:up`.
2. Run `pnpm check:workspace` before deeper debugging.
3. Keep API behavior, OpenAPI annotations, and generated client output in sync.
4. Run focused tests for the area changed, then run the relevant workspace gate.

## Pull Request Expectations

- Describe the domain and user workflow affected.
- Note API contract changes and include regenerated client files.
- Include test evidence or explain why a test was not practical.
- Keep generated shadcn primitives separate from Atomy-Q domain components.

## Ownership Rules

- Laravel Modules own backend domain behavior.
- `packages/api-client` owns generated API types and hooks.
- `packages/types` is for stable hand-authored shared types only.
- `apps/web/src/components/ui` is shadcn source code, not a domain layer.
- Domain-specific UI vocabulary belongs in features or domain components.
EOF

cat <<'EOF' > AGENTS.md
# AGENTS.md

This repository is meant to be maintainable by ordinary developers, not only by
the original maintainer or AI agents.

## Working Rules

- Read the closest README before editing a folder.
- Keep backend domain code inside the owning Laravel Module.
- Keep frontend workflow code inside the owning feature folder.
- Do not place product logic in shadcn primitives.
- Do not hand-edit generated Orval output unless debugging a generator issue.
- Regenerate `packages/api-client` after API contract changes.
- Prefer small, reviewable changes with test evidence.

## Verification

- API behavior: `pnpm test:api`
- Web behavior: `pnpm test:web`
- Generated client drift: `pnpm generate:api`
- Workspace health: `pnpm check:workspace`
EOF

cat <<'EOF' > docs/architecture.md
# Architecture

## Overview

The workspace is split into three primary layers:

- `apps/api`: Laravel API, grouped by business domain with Laravel Modules.
- `apps/web`: Next.js application, grouped by user-facing features.
- `packages/api-client`: generated TypeScript client from the API OpenAPI contract.

## Backend Boundaries

Each Laravel Module owns its controllers, requests, resources, actions, models,
policies, migrations, and tests for one domain. Shared framework glue can live
under `apps/api/app`, but domain behavior should not collect there.

## Frontend Boundaries

shadcn primitives live in `components/ui`. These files should stay generic.
Atomy-Q workflow components live in feature folders or domain/layout component
folders. Frontend server state should use generated API hooks or thin wrappers
around the generated client.

## Contract Flow

API annotations produce OpenAPI JSON. Orval consumes that JSON and writes
`packages/api-client`. Frontend code imports generated types and clients from
that package. Contract changes should include backend tests and generated client
updates in the same pull request.

## Local Services

Docker Compose provides Postgres, Redis, MinIO, and Mailpit. pgAdmin is
available through the optional `tools` profile.
EOF

cat <<EOF > docs/decisions/0001-monorepo-stack.md
# ADR 0001: Monorepo Stack

## Status

Accepted

## Decision

Use a pnpm/turbo monorepo with Laravel for the API, Next.js for the frontend,
Laravel Modules for backend domain grouping, shadcn/ui for frontend primitives,
and Orval for generated shared API clients and types.

## Rationale

This structure makes domain ownership explicit, keeps product-specific UI out of
primitive components, and gives frontend/backend integration a concrete contract
through OpenAPI generation.

## Consequences

- API modules must stay coherent and documented.
- OpenAPI annotations are part of backend delivery.
- Generated client drift is treated as a build/review issue.
- Developers can start the full local service stack with Docker Compose.
EOF

cat <<'EOF' > docs/api-contract.md
# API Contract Workflow

## Source of Truth

The Laravel API behavior and OpenAPI annotations are the source of truth.
Generated TypeScript clients are derived artifacts.

## Change Process

1. Change the Laravel route/controller/request/resource behavior.
2. Update or add OpenAPI annotations.
3. Run `pnpm generate:api`.
4. Use the generated types/hooks from `@atomy-q/api-client` in the frontend.
5. Commit backend changes and generated client changes together.

## Rules

- Do not create parallel handwritten frontend API types when Orval can generate
  the contract.
- Do not edit generated files as the permanent fix.
- If generated output looks wrong, fix the OpenAPI source.
EOF

# ----------------------------
# DOCKER LOCAL SERVICES
# ----------------------------

cat <<EOF > .env.docker.example
COMPOSE_PROJECT_NAME=$DB_NAME

POSTGRES_DB=$DB_NAME
POSTGRES_USER=postgres
POSTGRES_PASSWORD=postgres
POSTGRES_PORT=5432

REDIS_PORT=6379

MINIO_ROOT_USER=minioadmin
MINIO_ROOT_PASSWORD=minioadmin
MINIO_API_PORT=9000
MINIO_CONSOLE_PORT=9001
MINIO_BUCKET=$APP_NAME-local

MAILPIT_SMTP_PORT=1025
MAILPIT_HTTP_PORT=8025

PGADMIN_EMAIL=admin@example.com
PGADMIN_PASSWORD=admin
PGADMIN_PORT=5050
EOF

cp .env.docker.example .env.docker

mkdir -p .docker/minio

cat <<'EOF' > .docker/minio/create-bucket.sh
#!/bin/sh
set -eu

until mc alias set local "http://minio:9000" "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD"; do
  sleep 2
done

mc mb --ignore-existing "local/$MINIO_BUCKET"
mc anonymous set none "local/$MINIO_BUCKET"
EOF

chmod +x .docker/minio/create-bucket.sh

cat <<'EOF' > docker-compose.yml
name: ${COMPOSE_PROJECT_NAME:-atomy-q}

services:
  postgres:
    image: postgres:16-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${POSTGRES_DB:-atomy_q}
      POSTGRES_USER: ${POSTGRES_USER:-postgres}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-postgres}
    ports:
      - "${POSTGRES_PORT:-5432}:5432"
    volumes:
      - postgres-data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER:-postgres} -d ${POSTGRES_DB:-atomy_q}"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    command: ["redis-server", "--appendonly", "yes"]
    ports:
      - "${REDIS_PORT:-6379}:6379"
    volumes:
      - redis-data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  minio:
    image: minio/minio:latest
    restart: unless-stopped
    command: ["server", "/data", "--console-address", ":9001"]
    environment:
      MINIO_ROOT_USER: ${MINIO_ROOT_USER:-minioadmin}
      MINIO_ROOT_PASSWORD: ${MINIO_ROOT_PASSWORD:-minioadmin}
    ports:
      - "${MINIO_API_PORT:-9000}:9000"
      - "${MINIO_CONSOLE_PORT:-9001}:9001"
    volumes:
      - minio-data:/data

  minio-init:
    image: minio/mc:latest
    depends_on:
      - minio
    environment:
      MINIO_ROOT_USER: ${MINIO_ROOT_USER:-minioadmin}
      MINIO_ROOT_PASSWORD: ${MINIO_ROOT_PASSWORD:-minioadmin}
      MINIO_BUCKET: ${MINIO_BUCKET:-atomy-q-local}
    volumes:
      - ./.docker/minio/create-bucket.sh:/create-bucket.sh:ro
    entrypoint: ["/bin/sh", "/create-bucket.sh"]
    restart: "no"

  mailpit:
    image: axllent/mailpit:latest
    restart: unless-stopped
    ports:
      - "${MAILPIT_SMTP_PORT:-1025}:1025"
      - "${MAILPIT_HTTP_PORT:-8025}:8025"

  pgadmin:
    image: dpage/pgadmin4:latest
    profiles: ["tools"]
    restart: unless-stopped
    environment:
      PGADMIN_DEFAULT_EMAIL: ${PGADMIN_EMAIL:-admin@example.com}
      PGADMIN_DEFAULT_PASSWORD: ${PGADMIN_PASSWORD:-admin}
    ports:
      - "${PGADMIN_PORT:-5050}:80"
    volumes:
      - pgadmin-data:/var/lib/pgadmin
    depends_on:
      postgres:
        condition: service_healthy

volumes:
  postgres-data:
  redis-data:
  minio-data:
  pgadmin-data:
EOF

cat <<'EOF' > DOCKER.md
# Local Docker Services

This monorepo scaffold includes local infrastructure for the Laravel API and
Next.js frontend.

## Commands

```bash
pnpm docker:up      # Postgres, Redis, MinIO, Mailpit
pnpm docker:tools   # Adds pgAdmin
pnpm docker:logs
pnpm docker:down
```

## Services

| Service | URL / port | Purpose |
| --- | --- | --- |
| Postgres | `127.0.0.1:${POSTGRES_PORT:-5432}` | Primary relational database |
| Redis | `127.0.0.1:${REDIS_PORT:-6379}` | Cache, sessions, queues |
| MinIO API | `http://127.0.0.1:${MINIO_API_PORT:-9000}` | S3-compatible object storage |
| MinIO Console | `http://127.0.0.1:${MINIO_CONSOLE_PORT:-9001}` | Object storage admin UI |
| Mailpit SMTP | `127.0.0.1:${MAILPIT_SMTP_PORT:-1025}` | Local mail sink |
| Mailpit UI | `http://127.0.0.1:${MAILPIT_HTTP_PORT:-8025}` | Email preview UI |
| pgAdmin | `http://127.0.0.1:${PGADMIN_PORT:-5050}` | Optional Postgres admin UI |

Default local credentials live in `.env.docker.example`. Copy or edit
`.env.docker` for machine-local changes.

The Laravel API `.env.example` generated by the setup script is already wired
to these services for Postgres, Redis, MinIO/S3, and Mailpit.
EOF

# ----------------------------
# LARAVEL API
# ----------------------------

echo "Installing Laravel API..."

composer create-project laravel/laravel apps/api --no-interaction

cd apps/api

set_env_value() {
  local file="$1"
  local key="$2"
  local value="$3"

  if grep -q "^${key}=" "$file"; then
    sed -i -E "s|^${key}=.*|${key}=${value}|" "$file"
  else
    printf '%s=%s\n' "$key" "$value" >> "$file"
  fi
}

cat <<EOF > package.json
{
  "name": "api",
  "private": true,
  "scripts": {
    "dev": "php artisan serve --host=127.0.0.1 --port=8000",
    "build": "composer validate --no-check-publish",
    "lint": "vendor/bin/pint --test",
    "test": "php artisan test",
    "typecheck": "php artisan test --testsuite=Feature",
    "openapi": "php artisan l5-swagger:generate"
  }
}
EOF

composer config allow-plugins.wikimedia/composer-merge-plugin true
composer config --json extra.merge-plugin.include '["Modules/*/composer.json"]'

composer require \
  laravel/sanctum \
  laravel/cashier \
  nwidart/laravel-modules \
  darkaonline/l5-swagger \
  league/flysystem-aws-s3-v3 \
  predis/predis \
  --no-interaction

php artisan install:api --no-interaction || true
php artisan vendor:publish --provider="Nwidart\\Modules\\LaravelModulesServiceProvider" --no-interaction || true
php artisan vendor:publish --provider="L5Swagger\\L5SwaggerServiceProvider" --no-interaction || true

php artisan key:generate --ansi

cp .env .env.example

for env_file in .env .env.example; do
  set_env_value "$env_file" DB_CONNECTION pgsql
  set_env_value "$env_file" DB_HOST 127.0.0.1
  set_env_value "$env_file" DB_PORT 5432
  set_env_value "$env_file" DB_DATABASE "$DB_NAME"
  set_env_value "$env_file" DB_USERNAME postgres
  set_env_value "$env_file" DB_PASSWORD postgres
  set_env_value "$env_file" REDIS_CLIENT predis
  set_env_value "$env_file" REDIS_HOST 127.0.0.1
  set_env_value "$env_file" REDIS_PASSWORD null
  set_env_value "$env_file" REDIS_PORT 6379
  set_env_value "$env_file" CACHE_STORE redis
  set_env_value "$env_file" QUEUE_CONNECTION redis
  set_env_value "$env_file" SESSION_DRIVER redis
  set_env_value "$env_file" FILESYSTEM_DISK s3
  set_env_value "$env_file" AWS_ACCESS_KEY_ID minioadmin
  set_env_value "$env_file" AWS_SECRET_ACCESS_KEY minioadmin
  set_env_value "$env_file" AWS_DEFAULT_REGION us-east-1
  set_env_value "$env_file" AWS_BUCKET "$APP_NAME-local"
  set_env_value "$env_file" AWS_ENDPOINT http://127.0.0.1:9000
  set_env_value "$env_file" AWS_URL "http://127.0.0.1:9000/$APP_NAME-local"
  set_env_value "$env_file" AWS_USE_PATH_STYLE_ENDPOINT true
  set_env_value "$env_file" MAIL_MAILER smtp
  set_env_value "$env_file" MAIL_HOST 127.0.0.1
  set_env_value "$env_file" MAIL_PORT 1025
  set_env_value "$env_file" MAIL_USERNAME null
  set_env_value "$env_file" MAIL_PASSWORD null
  set_env_value "$env_file" MAIL_ENCRYPTION null
  set_env_value "$env_file" MAIL_FROM_ADDRESS "hello@$APP_NAME.local"
  set_env_value "$env_file" MAIL_FROM_NAME "$APP_NAME"
done

set_env_value .env.example APP_KEY ""

mkdir -p app/Http/Middleware app/Models/Concerns app/Shared/{DTOs,Traits,Enums,Exceptions,Helpers}

cat <<'EOF' > database/seeders/DemoTenantSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        // Add tenant/company/user seed records once the tenant model is defined.
    }
}
EOF

cat <<'EOF' > database/seeders/DevelopmentSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoTenantSeeder::class,
        ]);
    }
}
EOF

cat <<'EOF' > database/seeders/DatabaseSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DevelopmentSeeder::class,
        ]);
    }
}
EOF

cat <<'EOF' > app/Shared/README.md
# Shared API Code

Use this folder for framework-level helpers that are truly shared across
multiple modules.

Do not move domain behavior here just to avoid deciding which module owns it.
If a concept belongs to one workflow, keep it in that module.
EOF

cat <<'EOF' > app/Http/Middleware/SetTenantContext.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-ID') ?? $request->user()?->tenant_id;

        if ($tenantId !== null) {
            App::instance('tenant_id', $tenantId);
        }

        return $next($request);
    }
}
EOF

cat <<'EOF' > app/Models/Concerns/BelongsToTenant.php
<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            if (app()->bound('tenant_id')) {
                $query->where($query->getModel()->getTable().'.tenant_id', app('tenant_id'));
            }
        });

        static::creating(function ($model): void {
            if (app()->bound('tenant_id') && empty($model->tenant_id)) {
                $model->tenant_id = app('tenant_id');
            }
        });
    }
}
EOF

MODULES=(
  Rfq
  Quotation
  Project
  Vendor
  Approval
  EvidenceVault
  Reporting
  Metric
  Ai
  Billing
  Auth
  Admin
)

for module in "${MODULES[@]}"; do
  if php artisan module:make "$module" --no-interaction; then
    composer dump-autoload --no-scripts --no-interaction
  else
    mkdir -p "Modules/$module"/{app/Http/{Controllers,Requests,Resources},app/{Actions,Models,Policies},routes,database/{migrations,seeders},tests/Feature}
  fi

  cat <<EOF > "Modules/$module/README.md"
# $module Module

## Ownership

This module owns the $module domain inside the Laravel API.

## Folder Contract

| Path | Purpose |
| --- | --- |
| \`app/Actions\` | Single-purpose application actions |
| \`app/Data\` | DTOs and input/output data objects |
| \`app/Http/Controllers\` | HTTP entry points for this domain |
| \`app/Http/Requests\` | Validation and authorization for HTTP input |
| \`app/Http/Resources\` | API serialization for this domain |
| \`app/Models\` | Eloquent models owned by this domain |
| \`app/Policies\` | Authorization policies |
| \`app/Services\` | Domain services that coordinate multiple actions |
| \`database\` | Module migrations and seeders |
| \`routes\` | Module route files |
| \`tests\` | Module feature and unit tests |

## Rules

- Keep domain behavior in this module unless it is truly shared infrastructure.
- Add or update OpenAPI annotations when HTTP contracts change.
- Add feature tests for route behavior that changes user workflows.
EOF

  mkdir -p "Modules/$module/app/Data" "Modules/$module/app/Services"
done

composer dump-autoload --no-interaction

cat <<'EOF' > Modules/README.md
# API Modules

Backend domains are grouped with Laravel Modules. Each module should be small
enough to understand independently and explicit about the routes, data, policies,
and tests it owns.

Do not use `app/` as a dumping ground for domain logic. Use `app/` for shared
framework-level concerns and put business behavior in the owning module.
EOF

cat <<EOF > app/OpenApi.php
<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\\Info(
    version: '1.0.0',
    title: '$PHP_NAMESPACE API',
    description: 'OpenAPI contract used by Orval to generate shared frontend types and React Query clients.'
)]
#[OA\\Server(url: 'http://localhost:8000/api', description: 'Local API server')]
final class OpenApi
{
}
EOF

cd ../..

# ----------------------------
# GENERATED API CLIENT PACKAGE
# ----------------------------

mkdir -p packages/api-client/src/generated

cat <<EOF > packages/api-client/package.json
{
  "name": "@atomy-q/api-client",
  "version": "0.1.0",
  "private": true,
  "type": "module",
  "exports": {
    ".": "./src/index.ts"
  },
  "scripts": {
    "generate": "orval --config ../../orval.config.ts",
    "build": "tsc --noEmit",
    "lint": "tsc --noEmit",
    "typecheck": "tsc --noEmit"
  },
  "peerDependencies": {
    "@tanstack/react-query": "^5.0.0",
    "react": "^19.0.0"
  },
  "devDependencies": {
    "typescript": "^5.0.0"
  }
}
EOF

cat <<EOF > packages/api-client/tsconfig.json
{
  "compilerOptions": {
    "target": "ES2022",
    "lib": ["DOM", "DOM.Iterable", "ES2022"],
    "module": "ESNext",
    "moduleResolution": "Bundler",
    "jsx": "react-jsx",
    "strict": true,
    "skipLibCheck": true,
    "noEmit": true
  },
  "include": ["src"]
}
EOF

cat <<'EOF' > packages/api-client/src/fetcher.ts
declare const process:
  | {
      env?: Record<string, string | undefined>;
    }
  | undefined;

export type ApiClientOptions = {
  baseUrl?: string;
  token?: string;
  headers?: HeadersInit;
};

export class ApiError<T = unknown> extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly body: T,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export async function atomyQFetcher<T>(
  url: string,
  options: RequestInit = {},
  clientOptions: ApiClientOptions = {},
): Promise<T> {
  const envBaseUrl =
    typeof process !== 'undefined' ? process.env?.NEXT_PUBLIC_API_URL : undefined;
  const baseUrl =
    clientOptions.baseUrl ??
    envBaseUrl ??
    'http://localhost:8000/api';
  const headers = new Headers(options.headers);

  headers.set('Accept', 'application/json');

  if (options.body !== undefined && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  if (clientOptions.token) {
    headers.set('Authorization', `Bearer ${clientOptions.token}`);
  }

  new Headers(clientOptions.headers).forEach((value, key) => headers.set(key, value));

  const response = await fetch(`${baseUrl}${url}`, {
    ...options,
    headers,
  });

  const text = await response.text();
  const body = text ? JSON.parse(text) : undefined;

  if (!response.ok) {
    throw new ApiError(response.statusText || 'API request failed', response.status, body);
  }

  return body as T;
}
EOF

cat <<'EOF' > packages/api-client/src/index.ts
export * from './fetcher';
export * from './generated/atomy-q';
EOF

cat <<'EOF' > packages/api-client/src/generated/atomy-q.ts
// Placeholder until `pnpm generate:api` writes the Orval client.
export {};
EOF

cat <<'EOF' > packages/api-client/README.md
# API Client Package

This package contains the shared frontend API client generated by Orval from
the Laravel OpenAPI contract.

## Rules

- Generated files live under `src/generated`.
- Do not hand-edit generated files as the permanent fix.
- Fix incorrect output at the OpenAPI source, then rerun `pnpm generate:api`.
- Frontend code should import shared API types and generated hooks from this
  package instead of creating parallel handwritten API types.
EOF

cat <<'EOF' > packages/config/README.md
# Shared Config Package

Use this package for reusable workspace configuration that should be shared by
apps or packages, such as future ESLint, TypeScript, formatting, or test presets.

Do not put runtime business configuration here. Runtime app configuration belongs
in the owning app.
EOF

cat <<'EOF' > packages/types/README.md
# Shared Types Package

Use this package only for stable hand-authored TypeScript types that are shared
across apps and cannot be generated from the API contract.

Prefer generated types from `@atomy-q/api-client` for backend API payloads.
EOF

cat <<'EOF' > orval.config.ts
import { defineConfig } from 'orval';

export default defineConfig({
  atomyQ: {
    input: {
      target: './apps/api/storage/api-docs/api-docs.json',
    },
    output: {
      target: './packages/api-client/src/generated/atomy-q.ts',
      schemas: './packages/api-client/src/generated/model',
      mode: 'split',
      client: 'react-query',
      httpClient: 'fetch',
      clean: true,
      prettier: true,
      override: {
        fetch: {
          includeHttpResponseReturnType: false,
        },
        mutator: {
          path: './packages/api-client/src/fetcher.ts',
          name: 'atomyQFetcher',
        },
      },
    },
  },
});
EOF

# ----------------------------
# NEXT.JS + SHADCN FRONTEND
# ----------------------------

echo "Creating Next.js frontend..."

cd apps

pnpm dlx create-next-app@latest web \
  --typescript \
  --eslint \
  --app \
  --src-dir \
  --tailwind \
  --use-pnpm \
  --import-alias "@/*" \
  --yes

cd web

pnpm add @atomy-q/api-client@workspace:* @tanstack/react-query zod react-hook-form sonner lucide-react
pnpm add -D vitest jsdom @vitejs/plugin-react @testing-library/react @testing-library/jest-dom @playwright/test
pnpm pkg set scripts.test="vitest run --passWithNoTests"
pnpm pkg set scripts.test:unit="vitest run --passWithNoTests"
pnpm pkg set scripts.test:e2e="playwright test --pass-with-no-tests"
pnpm pkg set scripts.typecheck="tsc --noEmit"

cat <<'EOF' > next.config.ts
import type { NextConfig } from "next";
import path from "node:path";

const nextConfig: NextConfig = {
  turbopack: {
    root: path.join(__dirname, "../.."),
  },
};

export default nextConfig;
EOF

pnpm dlx shadcn@latest init --preset b6G3cs1Oa --yes
pnpm dlx shadcn@latest add button card dialog form field input input-group label select textarea checkbox switch radio-group toggle-group badge tabs table dropdown-menu navigation-menu sheet drawer popover tooltip separator scroll-area skeleton alert alert-dialog avatar command breadcrumb sidebar progress pagination empty spinner calendar chart sonner

cat <<'EOF' > src/components/ui/spinner.tsx
import { cn } from "@/lib/utils"
import { HugeiconsIcon } from "@hugeicons/react"
import { Loading03Icon } from "@hugeicons/core-free-icons"

type SpinnerProps = Omit<React.ComponentProps<"svg">, "strokeWidth"> & {
  strokeWidth?: number
}

function Spinner({ className, strokeWidth = 2, ...props }: SpinnerProps) {
  return (
    <HugeiconsIcon icon={Loading03Icon} strokeWidth={strokeWidth} role="status" aria-label="Loading" className={cn("size-4 animate-spin", className)} {...props} />
  )
}

export { Spinner }
EOF

mkdir -p \
  src/components/domain \
  src/components/layout \
  src/features/{rfq,vendors,projects,approvals,auth,billing,reporting,metrics,ai} \
  src/lib/{api,auth} \
  src/providers \
  tests/e2e

cat <<'EOF' > src/components/README.md
# Components

## Folder Contract

- `ui`: shadcn-generated primitives. Keep these generic.
- `domain`: reusable Atomy-Q product components.
- `layout`: shell, navigation, page layout, and workspace layout components.

Do not put business workflow logic in `ui`. Feature-specific components should
live in the owning feature folder first.
EOF

cat <<'EOF' > src/components/ui/README.md
# shadcn/ui Primitives

This folder is owned by the shadcn CLI. Components here should stay generic and
portable. Do not add Atomy-Q domain rules, workflow state, or API calls here.

The scaffold initializes shadcn with preset `b6G3cs1Oa`. Keep future component
updates aligned with that preset unless the team records a new design decision.
EOF

cat <<'EOF' > src/components/domain/README.md
# Domain Components

Use this folder for reusable Atomy-Q UI vocabulary such as status badges,
section headers, empty states, owner cells, process tracks, and document
previews.

Feature-specific components should live in `src/features/<feature>` unless they
are reused across multiple domains.
EOF

cat <<'EOF' > src/features/README.md
# Features

Each feature folder owns the pages, hooks, components, schemas, and tests for one
user-facing workflow.

Suggested shape:

```text
src/features/<feature>/
  components/
  hooks/
  schemas/
  utils/
  __tests__/
```

Use generated API types from `@atomy-q/api-client` instead of duplicating backend
payload types.
EOF

cat <<'EOF' > src/lib/api/README.md
# API Integration

Use this folder for thin application-level API helpers, auth header wiring, and
error normalization around `@atomy-q/api-client`.

Do not create a second handwritten API client here. If the generated contract is
wrong, fix the backend OpenAPI source and regenerate.
EOF

cat <<'EOF' > src/lib/env.ts
import { z } from 'zod';

const clientEnvSchema = z.object({
  NEXT_PUBLIC_API_URL: z.string().url().default('http://localhost:8000/api'),
});

export const clientEnv = clientEnvSchema.parse({
  NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL,
});
EOF

cat <<'EOF' > vitest.config.ts
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./vitest.setup.ts'],
  },
});
EOF

cat <<'EOF' > vitest.setup.ts
import '@testing-library/jest-dom/vitest';
EOF

cat <<'EOF' > playwright.config.ts
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:3000',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
EOF

cat <<'EOF' > src/providers/README.md
# Providers

Use this folder for app-wide React providers such as TanStack Query, auth
context, theme, and toast wiring.

Providers should compose infrastructure; they should not own feature-specific
business workflows.
EOF

cat <<'EOF' > tests/README.md
# Frontend Tests

- Unit and component tests should live near the feature/component they cover.
- End-to-end tests live in `tests/e2e`.
- Prefer testing user workflows over implementation details.
EOF

cd ../..

# ----------------------------
# CI PIPELINE
# ----------------------------

mkdir -p .github/workflows

cat <<EOF > .github/workflows/ci.yml
name: CI

on:
  push:
  pull_request:

jobs:
  build:
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_DB: $DB_NAME
          POSTGRES_USER: postgres
          POSTGRES_PASSWORD: postgres
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pdo_pgsql

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: 20

      - name: Setup pnpm
        uses: pnpm/action-setup@v4
        with:
          version: 9.15.0

      - name: Install root dependencies
        run: pnpm install --frozen-lockfile

      - name: Install API dependencies
        working-directory: apps/api
        run: composer install --no-interaction --prefer-dist --no-progress

      - name: Prepare API environment
        working-directory: apps/api
        run: |
          cp .env.example .env
          php artisan key:generate

      - name: Run API migrations
        working-directory: apps/api
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: $DB_NAME
          DB_USERNAME: postgres
          DB_PASSWORD: postgres
        run: php artisan migrate --force

      - name: Build workspace
        run: pnpm build
EOF

echo ""
echo "Atomy-Q stack scaffold ready: $APP_NAME"
echo ""
echo "Modules:"
printf -- "- %s\n" "${MODULES[@]}"
echo ""
echo "Next steps:"
echo "1. cd $APP_NAME"
echo "2. pnpm install"
echo "3. pnpm generate:api after adding OpenAPI annotations to the API routes/controllers"
echo "4. pnpm dev"
