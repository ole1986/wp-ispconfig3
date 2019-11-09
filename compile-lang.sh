#!/bin/bash

handleName="ole1986-ispconfig-blocks"


echo "Checking for npm packages 'po2json'..."

hash po2json
if [ $? != 0 ]
then
    echo "WARNING: npm package po2json not found."
    SKIP_SCRIPTS=1
fi

compatible=`po2json | egrep "format.*jed1.x"`

if [[ ! $compatible ]]
then
    echo "WARNING: The version of po2json does not support jed1.x."
    SKIP_SCRIPTS=1
fi


echo "Clearing previous compiled languages..."
rm -f languages/*.mo

if [[ ! $SKIP_SCRIPTS ]]
then
    echo "Clearing previous script translations..."
    rm -f languages/*.json
else
    echo "Script translations skipped"
fi

echo

for f in languages/*.po
do
    echo "Compile language $f into ${f:0:-3}.mo"
    msgfmt -v $f -o "${f:0:-3}.mo" 2> /dev/null
    if [ -z $SKIP_SCRIPTS ]
    then
        echo "Converting $f into json format"
        po2json $f "${f:0:-3}-$handleName.json" -f jed1.x --pretty
    fi
done