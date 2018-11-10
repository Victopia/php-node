# CMD: TAG=vicary/php-node eval 'docker build -f .private/Dockerfile -t $TAG . && docker push $TAG'

FROM php:apache

RUN apt-get update && apt-get -qq upgrade
RUN apt-get -qq install apt-utils
RUN docker-php-ext-install pdo_mysql
RUN apt-get -qq install libicu-dev && docker-php-ext-install intl
RUN docker-php-ext-install gettext
RUN apt-get -qq install libpng-dev && docker-php-ext-install gd
RUN docker-php-ext-install mysqli
# RUN pecl install mongodb
RUN apt-get clean
RUN a2enmod rewrite ssl remoteip
RUN a2ensite default-ssl
RUN openssl req -x509 -nodes -days 3650 -newkey rsa:2048 -subj "/C=US/ST=Other/L=L/O=snakeoil/CN=localhost" -out /etc/ssl/certs/ssl-cert-snakeoil.pem -keyout /etc/ssl/private/ssl-cert-snakeoil.key

EXPOSE 443

# Remove PHP source to save disk space
RUN rm -rf /usr/src
