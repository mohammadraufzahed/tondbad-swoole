# Contributing to Tondbād Swoole

Thank you for your interest in contributing! This document outlines the process and guidelines for contributing to the project.

## How to Contribute

1. **Fork the repository** and create a new branch for your changes.
2. **Make your changes** following the coding standards below.
3. **Run the test suite** and ensure everything passes:

   ```bash
   composer test
   ```

4. **Lint all PHP files** to catch syntax errors:

   ```bash
   find . -path ./vendor -prune -o -name '*.php' -exec php -l {} \;
   ```

5. **Validate composer.json**:

   ```bash
   composer validate --strict
   ```

6. **Commit your changes** with clear, descriptive messages.
7. **Open a pull request** against the `main` branch and describe the changes and why they are needed.

## Coding Standards

- Use `declare(strict_types=1);` at the top of every PHP file.
- Prefer explicit type hints and return types.
- Follow PSR-4 autoloading conventions.
- Keep changes focused and minimal.
- Add or update tests for bug fixes and new features.
- Avoid committing `.env` files, log files, or cache files.

## Reporting Issues

When reporting a bug, please include:

- A clear description of the issue.
- Steps to reproduce.
- Your PHP and OpenSwoole versions.
- Any relevant logs or error messages.

## Development Environment

The project requires:

- PHP 8.2+
- `ext-openswoole`
- `ext-pcntl`
- Composer

Run the HTTP server with:

```bash
composer server
```

Run the gRPC server with:

```bash
composer grpc
```

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
