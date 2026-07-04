# AiRep24 for WooCommerce

WooCommerce connector plugin for AiRep24.

The plugin is intentionally thin. It does not duplicate the AiRep24 assistant logic. It connects a WooCommerce store to the shared AiRep24 backend, inserts the widget script, syncs store knowledge, and exposes familiar settings inside WordPress admin.

## Current Scope

- WordPress admin page for connection, assistant, widget, knowledge and billing.
- Storefront widget auto-injection.
- WooCommerce product sync payload.
- Product create/update/delete events.
- Theme CSS color and gradient signals.
- Trial and checkout links routed to AiRep24.

## Backend Contract To Finalize

- `GET /api/woocommerce/config`
- `POST /api/woocommerce/config`
- `POST /api/woocommerce/sync`
- `POST /api/woocommerce/events`
- Hosted onboarding at `/auth/register?platform=woocommerce`
- Hosted checkout at `/billing/checkout?platform=woocommerce`

All billing, limits, voice, avatars, Telegram and knowledge behavior should remain centralized in AiRep24 backend.
