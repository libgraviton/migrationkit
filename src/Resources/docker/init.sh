#!/bin/sh

PUID=${PUID:-911}
PGID=${PGID:-911}

addgroup -g ${PGID} app && \
adduser -h /app -D -H -s /bin/ash -u ${PUID} -G app app && \
adduser app users

exec su-exec app "$@"
