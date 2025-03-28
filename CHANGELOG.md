# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2023-03-25

### Added
- Integration with Brick\PhoneNumber for improved phone validation
- Automated GitHub release workflow with dependencies included
- Better error messages for invalid phone numbers

### Changed
- Refactored phone validation to use Brick\PhoneNumber library
- Improved update mechanism to use GitHub releases
- Enhanced code organization with Composer autoloading

### Fixed
- Issue with validation of international phone numbers
