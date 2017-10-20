#!/usr/bin/env bash

LOCAL_USERNAME=`whoami`
REVISION=`git log -n 1 --pretty=format:"%H"`


function source(){
    set -a
    . .env
    set +a
}

function callRollBar(){
    curl https://api.rollbar.com/api/1/deploy/ \
      -F access_token=$ROLLBAR_KEY \
      -F environment=$ROLLBAR_ENV \
      -F revision=$REVISION \
      -F local_username=$LOCAL_USERNAME
}

echo "source env file";
source;
echo "stop docker";
docker-compose stop
echo "docker rm";
docker-compose rm -vf
echo "git pull";
git pull
echo "docker up";
docker-compose up -d
echo "rollbar event call";
callRollBar;