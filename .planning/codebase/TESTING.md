# Testing

## Framework
- Pest 4 (PHP testing framework)
- Feature tests for end-to-end behavior
- Unit tests for isolated functionality

## Test Structure
- Tests in `tests/` directory
- Feature tests in `tests/Feature/`
- Unit tests in `tests/Unit/`
- Test cases organized by functionality

## Testing Practices
- Use of Laravel's testing helpers
- Database transactions for test isolation
- Factories for creating test data
- Mocking and stubbing for dependencies
- Assertions for verifying behavior

## Test Configuration
- PHPUnit configuration in `phpunit.xml`
- Pest configuration in `tests/Pest.php`
- Environment-specific test configurations

## Code Coverage
- Aim for high code coverage (especially for business logic)
- Focus on testing edge cases and error conditions
- Integration tests for API endpoints and complex workflows

## Test Execution
- Run tests with `php artisan test`
- Run specific tests with filtering
- Use `--compact` flag for concise output