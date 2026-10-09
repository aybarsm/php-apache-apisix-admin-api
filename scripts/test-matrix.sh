#!/usr/bin/env bash
# Runs unit + arch tests and static analysis on every supported PHP version.
# composer.lock is not committed, so dependencies are re-resolved per PHP version
# unless MATRIX_UPDATE=0. Set MATRIX_INTEGRATION=1 to also run the integration suite.
set -euo pipefail

cd "$(dirname "$0")/.."

VERSIONS=("${@:-8.3 8.4 8.5}")
COMPOSER_BIN="$(command -v composer)"
STATUS=0

for v in ${VERSIONS[*]}; do
    PHP="/opt/homebrew/opt/php@${v}/bin/php"
    if [[ ! -x "$PHP" ]]; then
        echo "!! PHP ${v} not found at ${PHP}" >&2
        STATUS=1
        continue
    fi

    echo "==> PHP $("$PHP" -r 'echo PHP_VERSION;')"
    if [[ "${MATRIX_UPDATE:-1}" == "1" ]]; then
        "$PHP" "$COMPOSER_BIN" update --no-interaction --quiet
    fi

    "$PHP" vendor/bin/pest --testsuite=unit,arch || STATUS=1
    "$PHP" -d memory_limit=1G vendor/bin/phpstan analyse --no-progress || STATUS=1

    if [[ "${MATRIX_INTEGRATION:-0}" == "1" ]]; then
        "$PHP" vendor/bin/pest --testsuite=integration || STATUS=1
    fi
done

exit "$STATUS"
