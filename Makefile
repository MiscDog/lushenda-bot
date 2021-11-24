SHELL := /bin/bash

build:
	git pull origin master
	-docker rmi -f lushenda_bot >/dev/null
	docker build . -t lushenda_bot

run:
	/usr/local/bin/docker-compose run -d lushenda_bot 2>&1 | tee -a notifier.log
	-docker container prune -f >/dev/null
