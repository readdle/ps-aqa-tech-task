# If you're changing this version, don't forget to update Makefile
FROM composer:2.9.3 AS vendors
WORKDIR /app
COPY . .
RUN composer install --no-scripts

#######
# WEB #
#######

# If you're changing this version, don't forget to update other images' versions
FROM readdle/php:8.5.5-apache-essentials AS php-web
ARG PECL_DEV_EXTENSIONS
ARG LOCAL_IP
ENV APACHE_DOCUMENT_ROOT="/var/www/html/public"

COPY .ops/apache/fqdn.conf /etc/apache2/conf-available/fqdn.conf
COPY .ops/apache/remoteip.conf /etc/apache2/conf-available/remoteip.conf
COPY .ops/apache/security.conf /etc/apache2/conf-available/security.conf
COPY .ops/apache/mpm_prefork.conf /etc/apache2/mods-available/mpm_prefork.conf

COPY .ops/php/php.ini /usr/local/etc/php/php.ini

COPY --from=vendors --chown=www-data:www-data /app/vendor ./vendor
COPY --chown=www-data:www-data . .

RUN apt-get update \
        && apt-get install -y --no-install-recommends --no-install-suggests \
      libcurl4-openssl-dev \
    && pear config-set php_ini /usr/local/etc/php/php.ini \
    && yes | pecl install $(echo "${PECL_DEV_EXTENSIONS}") \
    && sed -i -e 's/zend_extension="uuid.so"//g' /usr/local/etc/php/php.ini \
    && a2enmod headers remoteip rewrite \
    && a2enconf fqdn remoteip security \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && install -d -o www-data -g www-data /run/sessions \
    && rm /var/log/apache2/access.log \
    && rm /var/log/apache2/other_vhosts_access.log \
    && ln -s /dev/null /var/log/apache2/access.log \
    && ln -s /dev/null /var/log/apache2/other_vhosts_access.log \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

#######
# CLI #
#######

# If you're changing this version, don't forget to update other images' versions and the Makefile
FROM readdle/php:8.5.5-cli-essentials AS php-cli
WORKDIR /app

COPY .ops/php/php.ini /usr/local/etc/php/php.ini

COPY --from=vendors --chown=www-data:www-data /app/vendor ./vendor
COPY --chown=www-data:www-data . .

RUN apt-get update \
    && apt-get install -y --no-install-recommends --no-install-suggests \
    && pear config-set php_ini /usr/local/etc/php/php.ini \
    && echo 'zend_extension="xdebug.so"' >> /usr/local/etc/php/conf.d/xdebug.ini \
    && install -d -o www-data -g www-data var/cache var/log \
    && rm -rf .ops \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

USER www-data
