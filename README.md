# Beaver Plugin — SMS

SMS sending plugin for the [Beaver Framework](https://github.com/Frank-Onidesk/beaver-framework),
through the EZ4U API (or any compatible provider).

## Features

- Send individual or bulk SMS (`POST /sms/send-list`).
- Record every attempt as a CSMS (SMS sending record).
- Test form at `/sms/test` (dev only).
- pt/en translations (namespace `sms::`).

## Installation

1. Place this folder at `beaver-plugins/sms/` (dev mode) or
   `app/plugins/sms/` (prod mode) inside the framework.

2. Install dependencies:

   ```bash
   cd beaver-plugins/sms
   composer install

3. Add the variables to the **framework's** `.env` file.

   ⚠️ The framework's `.env` is a **local configuration file** —
   it lives on your machine, at the framework root, and is **never
   committed to git**. You just edit it locally.

   You do **not** need to publish the framework to install this
   plugin. The plugin is self-contained; the framework only needs
   to exist on the same machine.

   Path example for a typical dev setup:

       /path/to/beaver-framework/.env

   Variables to add:

   ```dotenv
   SMS_PROVIDER_URL=...
   SMS_PROVIDER_ACCOUNT=...
   SMS_PROVIDER_KEY=...
   SMS_PROVIDER_SENDER=...
   SMS_PROVIDER_ENVIO24=1
   SMS_PROVIDER_TTL=24