docker-build-base:
	docker build --tag brunofunnie/butecobot-php:amd64-latest -f docker/base.dockerfile --build-arg ARCH=amd64/ docker
	docker build --tag brunofunnie/butecobot-php:arm64v8-latest -f docker/base.dockerfile --build-arg ARCH=arm64v8/ docker
build-app:
	docker build --tag brunofunnie/butecobot-app:latest -f docker/app.dockerfile .
dev:
	docker build --tag brunofunnie/butecobot-app:latest -f docker/app.dockerfile .
	docker compose down butecobot; docker compose up butecobot
push:
	docker manifest create \
	brunofunnie/butecobot-php:latest \
	--amend brunofunnie/butecobot-php:amd64-latest \
	--amend brunofunnie/butecobot-php:arm64v8-latest
	docker manifest push brunofunnie/butecobot-php:latest