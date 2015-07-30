#!/bin/bash
php_pid=`ps -ef | egrep 'php-[f]pm'`
if [ "$php_pid" ]; then
	echo "$php_pid"
else 
	sudo php-fpm
fi

nginx_pid=`ps -ef | egrep 'ng[i]nx:'`
echo "$nginx_pid"
if [ "$nginx_pid" ]; then
	(cd "/Users/yangqing/Documents/nodejs/nginx/nginx" && sudo ./sbin/nginx -s reload)
else 
	(cd "/Users/yangqing/Documents/nodejs/nginx/nginx" && sudo ./sbin/nginx)
fi
exit;