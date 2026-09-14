# Deployment

- LaraGram applications are deployed to your own servers: behind a web server (Nginx + PHP-FPM) or served by LaraGram Surge, with queue workers, the scheduler, and (when used) MTProto sessions kept running by a process supervisor.
- Activate the `deploying-laragram` skill whenever deploying the application, configuring the webhook for production, setting up Surge, queue workers, the scheduler, or MTProto sessions on a server, or troubleshooting a production bot.
