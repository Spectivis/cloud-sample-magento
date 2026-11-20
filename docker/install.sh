#!/bin/sh
# Installs the store in the compose setup: Composer packages, then
# setup:install against the db, opensearch and valkey services, with cache
# and page cache in Valkey. Run it once: docker compose exec app sh docker/install.sh
set -eu
cd /app
[ -d vendor ] || composer install --no-interaction
if [ -f app/etc/env.php ]; then
	echo "app/etc/env.php exists, so the store is installed; running setup:upgrade"
	exec php bin/magento setup:upgrade
fi
php bin/magento setup:install --no-interaction \
	--base-url="$MAGENTO_BASE_URL" \
	--db-host="$DB_HOST" --db-name="$DB_DATABASE" --db-user="$DB_USERNAME" --db-password="$DB_PASSWORD" \
	--search-engine=opensearch --opensearch-host="$OPENSEARCH_HOST" --opensearch-port=9200 \
	--cache-backend=redis --cache-backend-redis-server="$REDIS_HOST" --cache-backend-redis-db=0 \
	--page-cache=redis --page-cache-redis-server="$REDIS_HOST" --page-cache-redis-db=1 \
	--session-save=db \
	--admin-firstname=Store --admin-lastname=Admin --admin-email=admin@example.com \
	--admin-user=admin --admin-password=Admin12345demo --backend-frontname=admin \
	--language=en_US --currency=USD --timezone=UTC --use-rewrites=1
