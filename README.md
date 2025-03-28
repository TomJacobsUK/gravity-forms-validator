# Gravity Forms Validator

A WordPress plugin that extends Gravity Forms with advanced validation capabilities including international phone number validation using the Brick\PhoneNumber library.

## Features

- Advanced phone number validation for international numbers
- Country code detection and validation
- International phone input with flags and country code selector
- Address validation

## Installation

### Option 1: Standard Installation (With Dependencies Included)

1. Download the latest release ZIP file with "with-dependencies" in the name from the [GitHub Releases page](https://github.com/TomJacobsUK/gravity-forms-validator/releases)
2. In your WordPress admin panel, go to Plugins → Add New
3. Click the "Upload Plugin" button at the top of the page
4. Upload the ZIP file
5. Activate the plugin

### Option 2: Developer Installation (Requires Composer)

1. Clone this repository to your WordPress plugins directory
2. Navigate to the plugin directory:
   ```
   cd wp-content/plugins/gravity-forms-validator
   ```
3. Install dependencies using Composer:
   ```
   composer install --no-dev --optimize-autoloader
   ```
4. Activate the plugin in WordPress

## Updating

When updating the plugin:

1. If you installed using Option 1 (with dependencies included), you can update through WordPress as usual
2. If you installed using Option 2, after updating you may need to run `composer install` again to update dependencies

## Requirements

- WordPress 5.0 or higher
- Gravity Forms 2.4 or higher
- PHP 7.1 or higher
- Composer (for developer installation only)

## Development

### Release Process

This plugin uses GitHub releases for updates. To create a new release:

1. Update the version number in `gravity-forms-validator.php`
2. Create and push a new tag:
   ```
   git tag v1.0.2
   git push origin v1.0.2
   ```
3. GitHub Actions will automatically build the plugin with and without dependencies and create a release

### Composer Dependencies

This plugin uses the following Composer dependencies:

- [brick/phonenumber](https://github.com/brick/phonenumber): For advanced phone number validation