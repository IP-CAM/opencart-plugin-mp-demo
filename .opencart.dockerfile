FROM php:5.6-apache

ENV MYSQL_HOST db
ENV VIRTUAL_HOST 127.0.0.1


# Install netcat, required in wait for service script
RUN apt-get update && apt-get install -y \
    netcat \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

# Install mcrypt php extension
RUN apt-get update && apt-get install -y \
    libmcrypt-dev \
    && docker-php-ext-install -j$(nproc) mcrypt \
    && rm -rf /var/lib/apt/lists/*

# Install gd php extension
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng12-dev \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd \
    && rm -rf /var/lib/apt/lists/*

# Install mysqli php extension
RUN apt-get update && apt-get install -y \
    libmysqlclient-dev \
    && docker-php-ext-install -j$(nproc) mysqli \
    && rm -rf /var/lib/apt/lists/*

# Install zip extension
RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    && docker-php-ext-install -j$(nproc) zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /

COPY /utils/vhost.conf /tmp/vhost.conf

RUN apt-get update

RUN apt-get install wget -y
RUN apt-get install unzip -y

RUN mv /tmp/vhost.conf etc/apache2/sites-available/000-default.conf

RUN mkdir /tmp/opencart

WORKDIR /tmp/opencart

RUN wget https://github.com/opencart/opencart/archive/master.zip -P /tmp/opencart

RUN unzip master.zip

RUN ls -la /tmp/opencart

RUN cp -R /tmp/opencart/opencart-master/upload/* /var/www

RUN chmod -R 777 /var/www/

RUN mv /var/www/config-dist.php /var/www/config.php
RUN mv /var/www/admin/config-dist.php /var/www/admin/config.php

RUN ls -la /var/www/
