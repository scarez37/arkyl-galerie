FROM php:8.2-apache

# 1. Installer les outils PostgreSQL et unzip
RUN apt-get update && apt-get install -y libpq-dev unzip git \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# 2. Activer le module de réécriture Apache (souvent utile en PHP)
RUN a2enmod rewrite

# 3. Récupérer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Copier le code
COPY . /var/www/html/

# 5. Installer les dépendances (Stripe, etc.)
RUN cd /var/www/html && composer install --no-dev --optimize-autoloader

# 6. Donner les permissions
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# 🚀 FIX POUR RAILWAY : Configurer le port dynamiquement
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

CMD ["apache2-foreground"]
