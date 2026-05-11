FROM php:8.2.12-apache

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		git \
		unzip \
		libpq-dev \
		libsqlite3-dev \
	&& docker-php-ext-install pdo pdo_mysql pdo_pgsql pdo_sqlite \
	&& a2enmod rewrite headers \
	&& rm -rf /var/lib/apt/lists/*


RUN printf '%s\n' '<Directory /var/www/html>' '    AllowOverride All' '    Require all granted' '</Directory>' > /etc/apache2/conf-available/allow-override.conf \
	&& a2enconf allow-override

WORKDIR /var/www/html

COPY composer.json composer.lock ./
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

COPY . .

RUN if [ -f .env.production ] && [ ! -f .env ]; then cp .env.production .env; fi \
	&& chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
