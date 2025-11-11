FROM php:8.2-apache

# enable PDO Sqlite
RUN apt-get update && apt-get install -y libsqlite3-dev   && docker-php-ext-install pdo_sqlite

# copy source
COPY ./src/ /var/www/html/

# ensure write perms for sqlite and logs
RUN chown -R www-data:www-data /var/www/html   && chmod -R 770 /var/www/html

EXPOSE 80
