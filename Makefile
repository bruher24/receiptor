build:
	docker compose build --no-cache
	docker compose run --rm --no-deps php composer install

migration:
	docker compose exec php php bin/console make:migration

migrate:
	docker compose exec php php bin/console doctrine:migrations:migrate

entity:
	docker compose exec php php bin/console make:entity

up:
	docker compose up --scale ocr-worker=4 --scale groq-worker=1 --scale cancel-worker=1

clear:
	docker compose exec php php bin/console cache:clear
