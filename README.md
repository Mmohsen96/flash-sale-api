# Flash Sale Checkout — README

## What this repo contains
- Laravel 12 app (API-only) implementing a flash-sale checkout:
  - `GET  /api/products/{id}` — product info + `available_stock`
  - `POST /api/holds` — create temporary hold (~2 minutes)
  - `POST /api/orders` — create order from hold (pre_payment)
  - `POST /api/payments/webhook` — idempotent payment webhook
  - `GET  /api/metrics` — lightweight metrics endpoint
  - Scheduler commands:
    - `holds:expire` — expire holds
    - `webhooks:retry` — retry out-of-order webhooks

## How to run locally
Pre-reqs: PHP, Composer, MySQL, XAMPP (Windows) or standard LAMP/MAMP

1. Clone repo
2. php artisan migrate --seed
3. php artisan serve
4. php artisan schedule:work


## How to run Tests

- php artisan test


