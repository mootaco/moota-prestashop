#!/bin/sh

if [ ! -z $1 ]
then
  TARGET=$1

  if [ ! -d "$TARGET/modules" ]
  then
    echo "ERROR: There is no \`modules\` folder inside of $TARGET/"
  else
    ln -s `pwd` "$TARGET/modules/mootapay"
    echo "DONE"
  fi

else
  echo "Usage: ./link.sh <TARGET_DIR>"
fi
