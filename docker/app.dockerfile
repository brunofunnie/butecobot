FROM brunofunnie/chorumebot-php:latest

WORKDIR /app

COPY app/ .
RUN composer install --no-dev --optimize-autoloader --no-interaction

ENTRYPOINT ["/entrypoint.sh"]
