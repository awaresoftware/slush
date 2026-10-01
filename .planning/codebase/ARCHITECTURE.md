# Architecture

## Design Patterns
- MVC (Model-View-Controller) pattern with Livewire components
- Service Provider pattern for Laravel extensions
- Repository pattern for data access abstraction

## Data Flow
1. User requests through HTTP routes
2. Controllers or Livewire components handle the request
3. Models interact with the database
4. Views (Blade templates) render the response
5. Livewire components provide reactive frontend behavior

## Abstractions
- Laravel's service container for dependency injection
- Form Request validation classes
- Middleware for request filtering
- Events and listeners for decoupled interactions

## Entry Points
- Web routes in `routes/web.php`
- API routes in `routes/api.php`
- Console commands in `app/Console/Commands/`
- Livewire components in `app/Livewire/`