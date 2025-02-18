FROM php:fpm-alpine
COPY . /usr/src/taskmanager
WORKDIR /usr/src/taskmanager
EXPOSE 8000
CMD [ "php", "-S", "0.0.0.0:8000" ]
