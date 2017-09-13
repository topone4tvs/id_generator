sniffer:
	vendor/bin/phpcs --report=diff --colors --report=summary --standard=style.xml --encoding=utf-8 --extensions=php ./src

fixer:
	vendor/bin/phpcbf  --standard=style.xml --extensions=php ./src

test:
	cd vendor/bin/ && phpunit  --configuration ../../phpunit.xml ../../src
