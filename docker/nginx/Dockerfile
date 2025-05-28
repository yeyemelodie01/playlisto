FROM nginx:alpine

RUN apk add --no-cache openssl

WORKDIR /var/www

# Copier les fichiers de configuration Nginx
COPY ./docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/nginx/default.conf /etc/nginx/sites-available/default.conf

EXPOSE 80 443

CMD ["nginx"]