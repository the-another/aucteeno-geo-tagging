IMAGE_NAME := aucteeno-geo-tagging-build
DOCKER_RUN := docker run --rm -v $(PWD):/app -w /app $(IMAGE_NAME)

.PHONY: docker-build install install-dev build lint format test all clean release

docker-build:
	docker build -t $(IMAGE_NAME) .

install: docker-build
	$(DOCKER_RUN) composer install --no-dev --prefer-dist --no-progress

install-dev: docker-build
	$(DOCKER_RUN) composer install --prefer-dist --no-progress
	$(DOCKER_RUN) npm ci

build: docker-build
	$(DOCKER_RUN) npm run build

lint: docker-build
	$(DOCKER_RUN) composer phpcs

format: docker-build
	$(DOCKER_RUN) composer phpcbf

test: docker-build
	$(DOCKER_RUN) composer test

all: install-dev build lint test

clean:
	rm -rf vendor node_modules dist build .phpunit.cache

release: install build
	mkdir -p build
	rm -rf build/aucteeno-geo-tagging
	mkdir -p build/aucteeno-geo-tagging
	cp -R aucteeno-geo-tagging.php includes dist README.md readme.txt \
		build/aucteeno-geo-tagging/
	cd build && zip -r aucteeno-geo-tagging.zip aucteeno-geo-tagging
