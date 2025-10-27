# 1. از ایمیج رسمی PHP 8.2 با FPM استفاده می‌کنیم
FROM php:8.2-fpm-alpine

# 2. نصب ابزارهای مورد نیاز سیستم
# libzip-dev و postgresql-dev برای اکستنشن‌های PHP لازمه
RUN apk add --no-cache \
    curl \
    nginx \
    supervisor \
    libzip-dev \
    zip \
    unzip \
    postgresql-dev

# 3. نصب اکستنشن‌های PHP (مخصوصاً pgsql برای اتصال به دیتابیس)
RUN docker-php-ext-install pdo pdo_pgsql zip

# 4. نصب Composer (مدیریت پکیج‌های PHP)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 5. تنظیم پوشه کاری داخل کانتینر
WORKDIR /var/www/html

# 6. کپی کردن فایل‌های پروژه به داخل کانتینر
COPY . .

# 7. نصب پکیج‌های Composer (بدون پکیج‌های توسعه)
RUN composer install --no-dev --no-interaction --no-scripts --optimize-autoloader

# 8. تنظیم دسترسی‌ها برای لاراول
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 9. کپی کردن فایل‌های تنظیمات Nginx و Supervisor
# (این فایل‌ها رو در پوشه docker می‌سازیم)
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 10. پورت 80 (که Nginx استفاده می‌کنه) رو باز کن
EXPOSE 80

# 11. دستور اجرا (استفاده از Supervisor برای اجرای همزمان Nginx و PHP-FPM)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
