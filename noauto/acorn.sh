#!/bin/sh

find . -type f -name '*.js' | while read file
do
    emsg=`node_modules/.bin/acorn --silent "$file"`;
    exit=$?
    if [ "$exit" != "0" ]; then
        echo "Error in $file: $emsg"
        exit $exit;
    fi
done

