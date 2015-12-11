#!/bin/sh

# TODO: pull these out of composer.json
DRUPALROOT=web
VENDOR=$DRUPALROOT/sites/all/vendor

# Only the slashes in $VENDOR
SLASHES="$(echo $VENDOR | tr -cd '/')"

# One endparen for every slash
ENDPARENS="$(echo $SLASHES | tr '/' ')')"

# One 'dirname' for every slash
DIRNAMES="$(echo $SLASHES | sed -e 's#/#dirname(#g')"


#
# We are doing the following conversion:
#
#  $baseDir = dirname(dirname(dirname(dirname($vendorDir))));
#  $baseDir = dirname(dirname(dirname($vendorDir)));
#
# We also take out the '/web/' at the beginning of each path
# involving $baseDir.
#
# These changes work together to compensate for the fact that
# the top-level directory ("web") disappears when we push just
# its contents up to Pantheon.
#
find "$VENDOR/composer" -name "autoload*php" -exec \
  sed -i "" \
    -e "s#baseDir = dirname.*#baseDir = $DIRNAMES\$vendorDir$ENDPARENS;#" \
    -e "s#baseDir . '/$DRUPALROOT/#baseDir . '/#" \
    {} \;
