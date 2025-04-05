FROM surnet/alpine-wkhtmltopdf:3.21.2-0.12.6-full

ADD https://github.com/dunglas/frankenphp/releases/download/v1.5.0/frankenphp-linux-x86_64 /usr/local/bin/frankenphp

RUN chmod +x /usr/local/bin/frankenphp

WORKDIR /app

COPY ./fonts /usr/share/fonts/extra
COPY ./index.php ./server.php /app/

EXPOSE 8080

ENTRYPOINT []

# Run FrankenPHP in worker mode
CMD ["frankenphp", "php-server", "--listen", "0.0.0.0:8080", "--no-compress", "/app/server.php"]