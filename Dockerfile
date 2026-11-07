FROM php:8.2-apache

# 1. Extensions PostgreSQL (Supabase) + outils pour Composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip git \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && rm -rf /var/lib/apt/lists/*

# 2. Apache : réécriture + en-têtes, et prise en compte du fichier .htaccess
RUN a2enmod rewrite headers \
    && sed -ri 's#AllowOverride None#AllowOverride All#g' /etc/apache2/apache2.conf

# 3. PHP : aucune erreur affichée aux visiteurs (elles vont dans les logs)
RUN { echo 'display_errors=Off'; echo 'log_errors=On'; echo 'expose_php=Off'; } > /usr/local/etc/php/conf.d/arkyl.ini

# 4. Composer (version majeure épinglée) puis dépendances (Stripe)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 5. Code du site
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# 6. Port dynamique (Railway / Render fournissent $PORT). On ne remplace QUE les
#    directives Apache concernées (l'ancien « s/80/…/g » modifiait tout fichier contenant « 80 »).
ENV PORT=80
RUN sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf

CMD ["apache2-foreground"]
