# Inertia Modal

[![Latest Version on Packagist](https://img.shields.io/packagist/v/emargareten/inertia-modal.svg?style=flat-square)](https://packagist.org/packages/emargareten/inertia-modal)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/emargareten/inertia-modal/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/emargareten/inertia-modal/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/emargareten/inertia-modal/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/emargareten/inertia-modal/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/emargareten/inertia-modal.svg?style=flat-square)](https://packagist.org/packages/emargareten/inertia-modal)

Inertia Modal is a Laravel package that lets you implement backend-driven modal dialogs for Inertia apps.

## Installation

You can install the package via composer:

```bash
composer require emargareten/inertia-modal
```

## Usage

```php
$inertiaModal = new Emargareten\InertiaModal();
echo $inertiaModal->echoPhrase('Hello, Emargareten!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [emargareten](https://github.com/emargareten)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
