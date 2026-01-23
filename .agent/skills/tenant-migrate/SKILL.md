---
name: Tenant Migration
description: Run tenant migrations using Laravel Sail
---

# Tenant Migration

This skill runs the tenant migrations using Laravel Sail.

## Usage

```bash
./vendor/bin/sail artisan tenants:migrate
```

## Details

This command ensures that migrations are run within the Docker container environment provided by Laravel Sail, avoiding connection issues with external database services.
