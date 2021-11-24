FROM composer:latest

WORKDIR /app
COPY . .

RUN composer install
CMD [ "php", "bot.php" ]
