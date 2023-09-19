# DeBee - Manage your database changes

## Installation

You can install the package via composer:

```bash
composer require apptimus-core/debee
```

## Configure your project

Connect your project with this command :

```bash
php artisan debee:connect
```

You can get project key with this command :

```bash
php artisan debee:project show
```

## Push & Pull

You can push & pull the database changes with this commands :

```bash
php artisan debee:push
php artisan debee:pull
```