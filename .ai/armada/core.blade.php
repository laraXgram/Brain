@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# LaraGram Armada

- This application can run in Docker with LaraGram Armada. When a `compose.yaml` with a `laragram.test` service exists, run commands through `./vendor/bin/armada` (for example `./vendor/bin/armada laragram migrate`, `./vendor/bin/armada composer ...`, `./vendor/bin/armada npm ...`) instead of the host's PHP, Composer, or Node.
- IMPORTANT: Activate `armada-development` when changing `compose.yaml`, `compose.production.yaml`, or the `ARMADA_*` variables, when choosing or debugging a Bot API server container, when adding Armada services or processes, or when containerizing the application for production.
- Never run `./vendor/bin/armada bot-api logout`, `down -v`, or `webhook:set` without the user's request: they change the live bot or delete data.
