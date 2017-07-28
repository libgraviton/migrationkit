FROM php:7-alpine

COPY . /app

RUN chmod a+x /app/bin/docker/init.sh && \
    apk add --no-cache tini su-exec curl git

ENTRYPOINT ["/sbin/tini", "--", "/app/bin/docker/init.sh"]
