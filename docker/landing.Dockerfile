FROM nginx:1.27-alpine

COPY docker/landing-nginx.conf /etc/nginx/conf.d/default.conf
COPY landing/ /usr/share/nginx/html/
