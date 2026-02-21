FROM php:8.2-apache

# 1. On récupère Composer (l'installateur de paquets PHP)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 2. On installe les outils PostgreSQL + zip/unzip (nécessaires pour Composer)
RUN apt-get update && apt-get install -y libpq-dev unzip git \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# 3. On copie tout ton code (y compris composer.json) dans le serveur
COPY . /var/www/html/

# 4. 🚀 LA NOUVELLE MAGIE : On installe Stripe avec Composer !
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader

# 5. On donne les bonnes permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

EXPOSE 80
