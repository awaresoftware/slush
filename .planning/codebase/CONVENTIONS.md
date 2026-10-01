# Code Conventions

## PHP Standards
- PSR-12 coding standards
- Explicit return type declarations for methods
- Use of PHP 8 features like constructor property promotion
- Proper use of namespaces

## Laravel Conventions
- Models in `app/Models/` directory with proper naming
- Controllers in `app/Http/Controllers/` following REST conventions
- Form Requests for validation in `app/Http/Requests/`
- Livewire components in `app/Livewire/` with proper naming convention

## Naming Conventions
- Classes use StudlyCase (e.g., `UserProfile`)
- Methods and variables use camelCase (e.g., `getUserProfile`)
- Constants use UPPER_SNAKE_CASE (e.g., `MAX_RETRY_COUNT`)

## File Structure
- Blade files organized by purpose in `resources/views/`
- Livewire components follow a clear naming structure
- Tests in `tests/` directory with appropriate grouping

## Documentation
- PHPDoc blocks for classes and methods
- Inline comments for complex logic
- Clear method and variable names that express intent

## Security
- Use of Laravel's built-in security features (CSRF, XSS protection)
- Proper validation and sanitization of inputs
- Secure handling of authentication and session management