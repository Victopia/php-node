FROM php:apache

RUN apt-get update && apt-get upgrade -y
RUN apt-get install -y apt-utils
RUN docker-php-ext-install pdo_mysql
RUN apt-get install -y libicu-dev && docker-php-ext-install intl
RUN docker-php-ext-install gettext
RUN apt-get install -y libpng-dev && docker-php-ext-install gd
RUN pecl install mongodb
RUN apt-get clean
RUN a2enmod rewrite

# Remove PHP source to save disk space
RUN rm -rf /usr/src
