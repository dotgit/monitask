#!/bin/sh

BASEDIR=$(dirname "$(realpath "$0")")

# set common environment
sed -i 's/^start_time = ".*"/start_time = "'`date +%F`'"/' "$BASEDIR/ini/monitask.ini"

# run os-specific script
SCRIPT="$BASEDIR/$(basename "$0" .sh)-$(uname -s |tr '[:upper:]' '[:lower:]').sh"
[ -x "$SCRIPT" ] && . "$SCRIPT" || echo "OS-specific configure script $SCRIPT not found"
