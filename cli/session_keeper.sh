#!/bin/bash
# it keeps the session alive

TOKEN=$(cat ../data/chatwork.token)
FUTURE='201231' # 2020.12.31
URL="https://kcw.kddi.ne.jp/gateway.php?cmd=get_update&_t=$TOKEN&last_id=${FUTURE}_9999999999"

# sends a request every 30 minutes
while true
do
    curl $URL --cookie ../data/chatwork.cookie 2>/dev/null
    sleep 1800
done
