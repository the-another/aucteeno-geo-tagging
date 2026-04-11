IMAGE_NAME := aucteeno-nexus-geo-tagging-build
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
	rm -rf build/aucteeno-nexus-geo-tagging
	mkdir -p build/aucteeno-nexus-geo-tagging
	cp -R aucteeno-nexus-geo-tagging.php includes dist README.md readme.txt \
		build/aucteeno-nexus-geo-tagging/
	cd build && zip -r aucteeno-nexus-geo-tagging.zip aucteeno-nexus-geo-tagging
