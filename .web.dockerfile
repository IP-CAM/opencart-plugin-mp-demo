FROM php:5.6-apache

ENV MYSQL_HOST db
ENV VIRTUAL_HOST 127.0.0.1


RUN apt-get update && apt-get install -y inetutils-ftp vim wget

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


# ============================
# FTP Section
# ============================

RUN wget -c https://download.pureftpd.org/pure-ftpd/releases/pure-ftpd-1.0.43.tar.gz
RUN tar -xzf pure-ftpd-1.0.43.tar.gz

RUN cd /pure-ftpd-1.0.43; ./configure optflags=--with-everything --with-privsep --without-capabilities
RUN cd /pure-ftpd-1.0.43; make; make install

RUN mkdir -p /etc/pure-ftpd/conf

RUN echo yes > /etc/pure-ftpd/conf/ChrootEveryone
RUN echo no > /etc/pure-ftpd/conf/PAMAuthentication
RUN echo yes > /etc/pure-ftpd/conf/UnixAuthentication
RUN echo "30000 30009" > /etc/pure-ftpd/conf/PassivePortRange
RUN echo "10" > /etc/pure-ftpd/conf/MaxClientsNumber

RUN useradd -m -s /bin/bash ftpuser
RUN echo ftpuser:ftppass |chpasswd

EXPOSE 20 21 30000 30001 30002 30003 30004 30005 30006 30007 30008 30009


# ============================
# Apache2 Section
# ============================

ADD /utils/vhost.conf /tmp/vhost.conf

RUN apt-get update

RUN apt-get install wget -y
RUN apt-get install unzip -y

RUN mv /tmp/vhost.conf etc/apache2/sites-available/000-default.conf

RUN mkdir /tmp/opencart

WORKDIR /tmp/opencart


# ============================
# Opencart Section
# ============================

RUN wget https://github.com/opencart/opencart/archive/master.zip -P /tmp/opencart

RUN unzip master.zip

RUN ls -la /tmp/opencart

RUN cp -R /tmp/opencart/opencart-master/upload/* /var/www

RUN chmod -R 777 /var/www/

RUN mv /var/www/config-dist.php /var/www/config.php
RUN mv /var/www/admin/config-dist.php /var/www/admin/config.php

RUN cp /var/www/php.ini /usr/local/etc/php/php.ini

RUN ls -la /var/www/
