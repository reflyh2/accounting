---
name: run-migration
description: Run migrations using Laravel Sail
---

# Run Migration

This skill runs the migrations using Laravel Sail.

## Usage
For central application
```bash
./vendor/bin/sail artisan migrate
```

For tenant application
```bash
./vendor/bin/sail artisan tenants:migrate
```

## Details

This command ensures that migrations are run within the Docker container environment provided by Laravel Sail, avoiding connection issues with external database services.
