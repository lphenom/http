# Contributing to lphenom/http

Thank you for your interest in contributing! Please follow these guidelines.

## Requirements

- PHP 8.1+
- Docker + Docker Compose (all tooling runs inside containers — do **not** use local PHP/Composer)
- Git

## Getting Started

```bash
git clone https://github.com/lphenom/http.git
cd http
make up        # build & start containers
make install   # install composer dependencies inside container
```

## Development Workflow

All commands run inside Docker:

```bash
make test   # run PHPUnit
make lint   # check code style (PHP-CS-Fixer dry-run)
make fix    # auto-fix code style
make stan   # run PHPStan level 8
make shell  # open bash in container
```

## Code Style

- **PSR-12** enforced via `php-cs-fixer`
- `declare(strict_types=1);` required in every PHP file
- No `eval`, `Reflection`, dynamic class loading, or variable variables (KPHP-compatibility)
- Line length limit: 120 characters

## Commits

- Use [Conventional Commits](https://www.conventionalcommits.org/): `feat:`, `fix:`, `chore:`, `test:`, `docs:`, `refactor:`
- Keep commits small and focused
- Push to `main` after each commit

## Pull Requests

1. Fork the repository
2. Create a feature branch: `git checkout -b feat/my-feature`
3. Run `make lint && make stan && make test` — all must pass
4. Open a PR against `main`

## Versioning

This project uses [Semantic Versioning](https://semver.org/).

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).

