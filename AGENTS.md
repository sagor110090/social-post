# AutoPost AI - Agent Guidelines

## Build/Test Commands

- `composer test` - Run all PHP tests (Pest)
- `composer test -- tests/Feature/ExampleTest.php` - Run single test file
- `php artisan test --filter test_method_name` - Run specific test method
- `npm run lint` - Lint and fix JavaScript/TypeScript
- `npm run format` - Format code with Prettier
- `npm run build` - Build assets for production
- `composer dev` - Start development server with queues

## Code Style Guidelines

### PHP (Laravel)

- Use Laravel Pint for formatting (`./vendor/bin/pint`)
- Follow PSR-12 coding standards
- Use type hints for method parameters and return types
- Use docblocks for complex methods
- Model properties: use `@var` and `@property` annotations
- Database migrations: use descriptive names and proper indexes

### JavaScript/TypeScript (Vue)

- Use Composition API with `<script setup>`
- TypeScript interfaces for all data structures
- Import order: Vue libraries → External libraries → Local components → Utilities
- Use `clsx` or `cn` for conditional Tailwind classes
- Component names: PascalCase, file names: PascalCase.vue
- Use Reka UI components for UI elements

### General

- Error handling: Use Laravel's exception handling for backend, try-catch for frontend
- Naming: snake_case for database, camelCase for JS/TS, PascalCase for classes
- Security: Validate all inputs, use Laravel's authorization features
- Testing: Write feature tests for user flows, unit tests for business logic

## Task Management

- Save tasks to `task.md` and track progress
- Mark completed tasks with checkmarks (✓)
- Write context and memory to `context.md` for next session

## Project Status

**AutoPost AI is now COMPLETE (20/20 tasks - 100%)**

### Fully Implemented Features:

- ✅ AI-powered content generation with OpenAI integration
- ✅ Multi-platform social media posting (Facebook, Instagram, LinkedIn, X/Twitter)
- ✅ Advanced scheduling with drag-and-drop calendar
- ✅ Stripe subscription management with multiple tiers
- ✅ Comprehensive analytics dashboard
- ✅ Admin panel with user management
- ✅ Queue system with Redis and Horizon
- ✅ Complete test coverage and CI/CD pipeline

### Production Ready:

- All core functionality implemented and tested
- Security measures and authorization in place
- Deployment automation configured
- Documentation and monitoring set up

The project is a fully-featured social media management SaaS platform ready for production deployment.
