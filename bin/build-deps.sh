#!/usr/bin/env bash
#
# Release dependency build.
#
# Produces the shippable, Strauss-prefixed vendor/ + vendor-prefixed/ from the
# committed composer.lock. Each step runs as its own top-level `composer`
# process on purpose: nesting these inside a single composer script (via
# `@prefix-deps` etc.) breaks the composer-runtime callbacks — strauss can't
# find Composer\Factory and Google\Task\Composer::cleanup silently skips
# trimming google/apiclient-services. Separate processes each get a clean
# composer runtime, so the callbacks work.
#
# Leaves untracked dev-toolchain + prefixed-runtime orphans in vendor/; those
# are excluded from the git-archive release. Never `git add vendor/` blindly.

set -euo pipefail

cd "$(cd "$(dirname "$0")/.." && pwd)"

# When invoked via `composer build-deps`, composer puts vendor/bin ahead on PATH,
# so a bare `composer` resolves to the in-project composer/composer bin (which
# fatals — the project autoloader excludes composer's own classes). Use the
# global composer that launched us (COMPOSER_BINARY), falling back to PATH when
# this script is run directly.
COMPOSER="${COMPOSER_BINARY:-composer}"

# 1. Install everything from the lock (dev included — Strauss is require-dev).
"$COMPOSER" install

# 2. Trim google services, prefix runtime deps into vendor-prefixed/, delete
#    the unprefixed originals from vendor/. (bin/strauss.php wipes the target
#    first for a deterministic rebuild.)
"$COMPOSER" prefix-deps

# 3. Rebuild the autoloader without dev packages or the now-deleted runtime.
"$COMPOSER" dump-autoload -o --no-dev

echo "build-deps: done."
