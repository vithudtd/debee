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

Push Database Changes:

```bash
php artisan debee:push
```

Pull Database Changes (excluding your changes):

```bash
php artisan debee:pull
```

Pull All Database Changes (including your changes):

```bash
php artisan debee:pull /all
```