build-base:
	docker build -t brunofunnie/chorumebot-php:latest -f docker/base.dockerfile docker
build-app:
	docker build -t brunofunnie/chorumebot-app:latest -f docker/app.dockerfile .