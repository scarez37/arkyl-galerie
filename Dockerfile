FROM php:8.2-apache

# 🛠️ LA MAGIE EST ICI : On installe les outils PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

EXPOSE 80
