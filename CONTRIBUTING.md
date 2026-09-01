# Contributing to Laravel LAN

Thank you for considering contributing to Laravel LAN!

## Development Setup

1. Fork and clone the repository.
2. Install Composer dependencies:
   ```bash
   composer install
   ```

## Running Tests

Run the PHPUnit test suite:

```bash
./vendor/bin/phpunit
```

Make sure all tests pass before submitting a pull request.

## Coding Standards

- Follow PSR-12 and standard Laravel conventions.
- Ensure all new features and bug fixes include corresponding unit or feature tests.
- Keep platform-specific code cleanly isolated inside dedicated detector or adapter classes.

## Pull Request Guidelines

1. Create a descriptive feature branch: `git checkout -b feature/my-new-feature`.
2. Commit your changes with clear, concise commit messages.
3. Ensure the test suite passes locally.
4. Submit a Pull Request targeting the `main` branch.
