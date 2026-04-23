#!/bin/sh
set -e

APP_DOMAIN="${APP_DOMAIN:-localhost}"
SSL_ENABLED="${SSL_ENABLED:-false}"
SSL_CERT_PATH="${SSL_CERT_PATH:-/etc/nginx/ssl/cert.pem}"
SSL_KEY_PATH="${SSL_KEY_PATH:-/etc/nginx/ssl/key.pem}"

if [ "$SSL_ENABLED" = "true" ]; then
    echo "Configuring Nginx with SSL for domain: $APP_DOMAIN"
    cp /etc/nginx/templates/https.conf.template /etc/nginx/conf.d/default.conf
    sed -i "s|SSL_CERT_PATH_PLACEHOLDER|${SSL_CERT_PATH}|g" /etc/nginx/conf.d/default.conf
    sed -i "s|SSL_KEY_PATH_PLACEHOLDER|${SSL_KEY_PATH}|g" /etc/nginx/conf.d/default.conf
else
    echo "Configuring Nginx without SSL for domain: $APP_DOMAIN"
    cp /etc/nginx/templates/http.conf.template /etc/nginx/conf.d/default.conf
fi

sed -i "s|SERVER_NAME_PLACEHOLDER|${APP_DOMAIN}|g" /etc/nginx/conf.d/default.conf

exec nginx -g 'daemon off;'
