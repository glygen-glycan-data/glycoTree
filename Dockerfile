FROM php:8.5.12-apache
RUN docker-php-ext-install mysqli
RUN mkdir -p /usr/share/man/man1/
# RUN apt-get update && apt-get install -y software-properties-common
RUN apt-get update && apt-get install -y default-jre
RUN echo 'SetEnv MYSQL_PASSWORD ${MYSQL_PASSWORD}' >> /etc/apache2/conf-enabled/environment.conf
RUN echo 'SetEnv MYSQL_SERVER_NAME mysql' >> /etc/apache2/conf-enabled/environment.conf
RUN echo 'memory_limit = 256M' >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini
