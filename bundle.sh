#!/bin/sh
# composer update --prefer-dist --no-dev

_name=mootapay
_pwd=`pwd`
_dir=$(basename $_pwd)

cd ../
mkdir tmp > /dev/null 2>&1
cd tmp/

rm -rf tmp/$_name > /dev/null 2>&1

cp -an $_pwd $_name

rm -rf $_name/.git > /dev/null 2>&1
rm -rf $_name/.vscode > /dev/null 2>&1

zip -qr9 $_name.zip $_name/
tar -cf $_name.tar $_name/
bzip2 -9 $_name.tar

rm -f $_pwd/$_name.zip > /dev/null 2>&1
rm -f $_pwd/$_name.tar.bz2 > /dev/null 2>&1

mv $_name.zip $_pwd/
mv $_name.tar.bz2 $_pwd/

cd ../
rm -rf tmp/

echo "Look for \`$_name.(zip|tar.bz2)\` inside \`$_pwd\`"
