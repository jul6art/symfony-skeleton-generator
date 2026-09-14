<p align="center">
    <a href="https://devinthehood.com"><img src="https://github.com/jul6art/symfony-skeleton-generator/blob/master/public/img/logo.png?raw=true" alt="logo dev in the hood" width="400"></a>
</p>

# {{PROJECT_TITLE}}

<p align="left">
    <img src="https://img.shields.io/static/v1?label=stable&message=v1&color=0ea5e9" alt="Version">
    <img src="https://img.shields.io/badge/php-{{PHP_VERSION}}-777bb4" alt="PHP {{PHP_VERSION}}">
    <img src="https://img.shields.io/badge/symfony-{{SYMFONY_VERSION}}-000000" alt="Symfony {{SYMFONY_VERSION}}">
    <img src="https://img.shields.io/badge/license-proprietary-red" alt="Proprietary">
</p>

Projet Symfony {{SYMFONY_VERSION}} généré en mode **{{MODE}}** le {{DATE}}.

Installation
------------

```shell
make install
make db-create
make db-migrate
make fixtures
```

Développer
----------

```shell
make start     # serveur local en https
make logs      # suit les logs
make stop
```

`make help` liste toutes les cibles.

Qualité
-------

```shell
make qa        # style de code + analyse statique + tests
```

Chacune se lance aussi séparément : `make cs` (et `make cs-fix`), `make stan`, `make test`.
