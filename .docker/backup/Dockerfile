# ==============================================================================
# Backup service — Alpine + mariadb-client + cron. Minim dependency.
# ==============================================================================

FROM alpine:3.20

RUN apk add --no-cache \
        bash \
        mariadb-client \
        gzip \
        tar \
        tzdata \
        findutils \
        coreutils \
        supercronic \
    && mkdir -p /backups/db /backups/files

COPY .docker/backup/backup.sh     /usr/local/bin/backup.sh
COPY .docker/backup/entrypoint.sh /usr/local/bin/entrypoint.sh
# Strip Windows CRLF (safety net) + make executable
RUN sed -i 's/\r$//' /usr/local/bin/backup.sh /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/backup.sh /usr/local/bin/entrypoint.sh

WORKDIR /backups

# Volume dari host untuk retensi backup
VOLUME ["/backups"]

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
