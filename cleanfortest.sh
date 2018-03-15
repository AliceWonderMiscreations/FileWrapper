#!/bin/bash

# removes backup files and does fresh composer install of all dependencies

find . -type f -print |grep "~$" |while read file; do
  rm -f ${file}
done

rm -f composer.lock

cat psr2.phpcs.xml > phpcs.xml

rm -rf vendor

composer install

if [ -x vendor/bin/phpunit ]; then
  vendor/bin/phpunit --testdox-text UnitTestResults.txt
fi
