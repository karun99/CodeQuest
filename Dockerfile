FROM php:8.2-apache
WORKDIR /var/www/html
COPY . /var/www/html/
RUN docker-php-ext-install mysqli pdo pdo_mysql 2>/dev/null || true
EXPOSE 80
