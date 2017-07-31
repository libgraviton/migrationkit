FROM php:7-alpine

COPY . /app
COPY src/Resources/docker/init.sh /

RUN chmod a+x /app/bin/docker/init.sh && \
    apk add --no-cache tini su-exec curl git

ENTRYPOINT ["/sbin/tini", "--", "/init.sh"]
