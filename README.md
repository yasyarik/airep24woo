# AiRep24 for WooCommerce

WooCommerce connector plugin for AiRep24.

The plugin is intentionally thin. It does not duplicate the AiRep24 assistant logic. It connects a WooCommerce store to the shared AiRep24 backend, inserts the widget script, syncs store knowledge, and exposes familiar settings inside WordPress admin.

## Scope

- WordPress admin page for connection, assistant, widget, knowledge and billing.
- One-click store connection that creates the AiRep24 tenant, bot and WooCommerce storefront channel.
- Storefront widget auto-injection.
- WooCommerce product, page, store metadata and theme color/gradient sync.
- Product create/update/delete events.
- Theme CSS color and gradient signals.
- Trial and checkout links routed to AiRep24.

## Backend Contract

- `POST /v1/woocommerce/connect`
- `GET /v1/woocommerce/config`
- `POST /v1/woocommerce/config`
- `POST /v1/woocommerce/sync`
- `POST /v1/woocommerce/events`
- Hosted onboarding at `/auth/register?platform=woocommerce`
- Hosted checkout at `/billing/checkout?platform=woocommerce`

All billing, limits, voice, avatars, Telegram and knowledge behavior should remain centralized in AiRep24 backend.
