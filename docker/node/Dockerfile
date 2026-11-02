FROM node:24-bookworm-slim

ARG LOCAL_UID=1000
ARG LOCAL_GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends gosu \
    && if [ "$LOCAL_GID" != "1000" ]; then groupmod -g "$LOCAL_GID" node; fi \
    && if [ "$LOCAL_UID" != "1000" ]; then usermod -u "$LOCAL_UID" node; fi \
    && chown -R node:node /home/node \
    && rm -rf /var/lib/apt/lists/*

COPY docker/node/entrypoint.sh /usr/local/bin/starter-entrypoint
COPY docker/node/vite-health.mjs /usr/local/bin/vite-health.mjs
RUN chmod +x /usr/local/bin/starter-entrypoint
WORKDIR /var/www/html
ENTRYPOINT ["starter-entrypoint"]
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
