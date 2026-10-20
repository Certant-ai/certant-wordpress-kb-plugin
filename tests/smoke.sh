#!/usr/bin/env bash
# Installs the built plugin into a throwaway WordPress (Docker) and checks, as
# an anonymous visitor, everything the Certant connector relies on.
#
# Usage: tests/smoke.sh [wordpress image tag, default latest]   e.g. 6.0, 7.1, latest
# Needs: docker, curl, jq, zip. Removes its containers when it exits.
set -euo pipefail

cd "$(dirname "$0")/.."
TAG="${1:-latest}"
CLI_IMAGE="${CLI_IMAGE:-wordpress:cli-2.11.0-php8.2}"
ID="certant-kb-smoke-$$"
NET="$ID" DB="$ID-db" SITE="$ID-wp"
ENV=(-e WORDPRESS_DB_HOST="$DB" -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD=wp -e WORDPRESS_DB_NAME=wp)

cleanup() {
	docker rm -f "$SITE" "$DB" >/dev/null 2>&1 || true
	docker network rm "$NET" >/dev/null 2>&1 || true
}
trap cleanup EXIT

fail() { echo "FAIL: $*" >&2; exit 1; }
pass() { echo "ok   $*"; }
wp() { docker run --rm --network "$NET" --volumes-from "$SITE" --user 33:33 "${ENV[@]}" "$CLI_IMAGE" wp "$@"; }
rest() { curl -sf "$URL/?rest_route=$1"; }   # anonymous; works with any permalink setting

# --- a WordPress with the plugin installed from the release zip -------------
ZIP="$(bin/build-zip.sh dist)"
docker network create "$NET" >/dev/null
docker run -d --name "$DB" --network "$NET" -e MARIADB_ROOT_PASSWORD=root \
	-e MARIADB_DATABASE=wp -e MARIADB_USER=wp -e MARIADB_PASSWORD=wp mariadb:11.4 >/dev/null
docker run -d --name "$SITE" --network "$NET" -p 127.0.0.1::80 "${ENV[@]}" "wordpress:$TAG" >/dev/null
PORT="$(docker port "$SITE" 80/tcp | head -1 | sed 's/.*://')"
URL="http://127.0.0.1:$PORT"

for _ in $(seq 60); do docker exec "$SITE" test -f /var/www/html/wp-settings.php 2>/dev/null && break; sleep 2; done
for _ in $(seq 60); do wp db check >/dev/null 2>&1 && break; sleep 2; done
wp core install --url="$URL" --title="Smoke test" --admin_user=admin --admin_password=smoke-test-only \
	--admin_email=admin@example.test --skip-email >/dev/null
echo "WordPress $(wp core version) at $URL"

docker cp "$ZIP" "$SITE:/var/www/html/wp-content/certant-knowledgebase.zip"
docker exec "$SITE" chown 33:33 /var/www/html/wp-content/certant-knowledgebase.zip
wp plugin install /var/www/html/wp-content/certant-knowledgebase.zip --activate >/dev/null
docker exec "$SITE" rm /var/www/html/wp-content/certant-knowledgebase.zip
pass "installed and activated from $ZIP"

# --- the taxonomy, as the REST API shows it to anyone -----------------------
tax="$(rest /wp/v2/taxonomies/certant_kb)" || fail "taxonomy not in the REST API"
[ "$(jq -r '.types | sort | join(",")' <<<"$tax")" = "attachment,page,post" ] \
	|| fail "applies to $(jq -c .types <<<"$tax"), expected post, page, attachment"
[ "$(jq -r .hierarchical <<<"$tax")" = "true" ] || fail "not hierarchical"
pass "certant_kb on posts, pages and media, hierarchical"

[ "$(wp eval 'echo is_taxonomy_viewable( "certant_kb" ) ? "yes" : "no";')" = "no" ] \
	|| fail "the taxonomy has public pages"
pass "no public archive pages"

KB="$(rest '/wp/v2/certant_kb&slug=knowledgebase' | jq -r '.[0].id // empty')"
[ -n "$KB" ] || fail "activation did not create the Knowledgebase collection"
SUB="$(wp term create certant_kb "HR policies" --parent="$KB" --porcelain)"
pass "first collection made on activation; sub-collection $SUB under it"

# --- content filed in a collection, and content that is not ----------------
IN_PAGE="$(wp post create --post_type=page --post_status=publish --post_title='Leave policy' --porcelain)"
OUT_PAGE="$(wp post create --post_type=page --post_status=publish --post_title='Contact us' --porcelain)"
IN_POST="$(wp post create --post_type=post --post_status=publish --post_title='Office move' --porcelain)"
wp post term set "$IN_PAGE" certant_kb "$SUB" --by=id >/dev/null
wp post term set "$IN_POST" certant_kb "$KB" --by=id >/dev/null
docker exec -u 33 "$SITE" sh -c 'echo "Leave policy, version 3." > /var/www/html/wp-content/leave-policy.txt'
FILE="$(wp media import /var/www/html/wp-content/leave-policy.txt --porcelain)"
wp post term set "$FILE" certant_kb "$KB" --by=id >/dev/null

ids() { rest "$1" | jq -r '[.[].id] | sort | join(",")'; }
[ "$(ids "/wp/v2/pages&certant_kb=$SUB")" = "$IN_PAGE" ] || fail "page filter returned $(ids "/wp/v2/pages&certant_kb=$SUB")"
[ "$(ids "/wp/v2/posts&certant_kb=$KB")" = "$IN_POST" ] || fail "post filter returned $(ids "/wp/v2/posts&certant_kb=$KB")"
[ "$(ids "/wp/v2/media&certant_kb=$KB")" = "$FILE" ] || fail "media filter returned $(ids "/wp/v2/media&certant_kb=$KB")"
[ "$(rest "/wp/v2/pages/$IN_PAGE" | jq -r '.certant_kb | join(",")')" = "$SUB" ] || fail "page does not list its collection"
pass "anonymous REST filters return only filed posts, pages and files"

echo "PASS (WordPress $TAG)"
