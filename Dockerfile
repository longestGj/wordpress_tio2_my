FROM wordpress:cli-php8.3@sha256:2b5e9d4d3e51909dca1aaa4732e9f5e5bf0377c2114dbd8ff39f060bff202586 AS wpcli

FROM wordpress:php8.3-apache@sha256:65919a9ca10940feb10d9400fead0d639bf86241f47c91e2b9ea4703aa8452cf

ARG VCS_REF
ARG BUILD_DATE
RUN case "$VCS_REF" in ''|*[!0-9a-f]*) exit 64;; esac \
 && test "${#VCS_REF}" -eq 40 \
 && test -n "$BUILD_DATE"

LABEL org.opencontainers.image.source="https://github.com/longestGj/wordpress_tio2_my" \
      org.opencontainers.image.revision="$VCS_REF" \
      org.opencontainers.image.created="$BUILD_DATE"

ENV TIO2_RELEASE="$VCS_REF"

COPY --from=wpcli /usr/local/bin/wp /usr/local/bin/wp
COPY wp-content/themes/tio2-malaysia /usr/src/wordpress/wp-content/themes/tio2-malaysia
COPY wp-content/plugins/tio2-content /usr/src/wordpress/wp-content/plugins/tio2-content
COPY content /opt/tio2/content
COPY scripts/bootstrap-production.php /opt/tio2/bin/bootstrap-production.php
COPY deploy/docker-entrypoint.sh /usr/local/bin/tio2-entrypoint.sh

RUN test -x /usr/local/bin/wp \
 && test -f /usr/src/wordpress/wp-content/themes/tio2-malaysia/style.css \
 && test -f /usr/src/wordpress/wp-content/plugins/tio2-content/tio2-content.php \
 && test -f /opt/tio2/content/initial-home.json \
 && test -f /opt/tio2/content/media/hero.png \
 && chmod 0755 /usr/local/bin/tio2-entrypoint.sh \
 && chown -R www-data:www-data /usr/src/wordpress/wp-content /opt/tio2

ENTRYPOINT ["/usr/local/bin/tio2-entrypoint.sh"]
CMD ["apache2-foreground"]
