# Coding Standards and Best Practices

## PHP Standards (PSR-12)

### File Structure

- Use 4 spaces for indentation (no tabs)
- Files must end with a single newline
- Lines should not exceed 120 characters
- Class names in PascalCase
- Method names in camelCase
- Constants in UPPER_SNAKE_CASE

### Class Declaration

```php
<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Implementation
    }
}
```

### Method Documentation

```php
/**
 * Create a new post.
 *
 * @param Request $request
 * @return JsonResponse
 * @throws ValidationException
 */
public function store(Request $request): JsonResponse
{
    // Implementation
}
```

## Laravel Specific Standards

### Controllers

- Single responsibility principle
- Maximum 20-25 methods per controller
- Use dependency injection
- Return consistent response types
- Proper HTTP status codes

### Models

- Use fillable property for mass assignment
- Define relationships explicitly
- Use casts for type conversion
- Implement scopes for complex queries
- Use accessors and mutators for data transformation

### Services

- Business logic separation
- Single responsibility per service
- Use dependency injection
- Handle errors gracefully
- Log important operations

### Validation

- Use Form Request classes
- Custom validation rules in separate classes
- Clear error messages
- Validate on both client and server side

## TypeScript Standards

### File Structure

- Use 2 spaces for indentation
- Semicolons required
- Single quotes for strings
- Interfaces for type definitions
- Enums for constants

### Component Structure

```typescript
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import type { Post } from '@/types'

interface Props {
  post: Post
  editable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  editable: false
})

const emit = defineEmits<{
  update: [post: Post]
  delete: [id: number]
}>()
</script>
```

### Type Definitions

```typescript
export interface Post {
    id: number;
    content: string;
    media?: Media[];
    scheduledAt?: string;
    createdAt: string;
    updatedAt: string;
}

export interface SocialAccount {
    id: number;
    platform: 'twitter' | 'facebook' | 'linkedin' | 'instagram';
    username: string;
    isActive: boolean;
}
```

## Vue.js Standards

### Component Naming

- PascalCase for component names
- Descriptive and concise
- Avoid HTML element names
- Use multi-word for root components

### Template Structure

- Use semantic HTML5 elements
- Proper accessibility attributes
- Consistent class naming (BEM or utility-first)
- Conditional rendering with v-if/v-show

### Reactivity

- Use ref for primitive values
- Use reactive for objects
- Computed properties for derived state
- Watch for side effects

## CSS/Tailwind Standards

### Class Organization

- Utility-first approach
- Responsive design first
- Consistent spacing scale
- Component-specific classes when needed

### Responsive Design

- Mobile-first approach
- Consistent breakpoints
- Touch-friendly interfaces
- Proper image optimization

## Database Standards

### Migration Naming

- Descriptive migration names
- Timestamp prefix
- Reversible migrations
- Index creation for performance

### Schema Design

- Proper foreign key constraints
- Appropriate column types
- Default values where applicable
- Nullable vs required fields

## Testing Standards

### Test Organization

- Describe behavior, not implementation
- Arrange-Act-Assert pattern
- Clear test names
- Independent tests

### Test Coverage

- Unit tests for business logic
- Feature tests for user flows
- Integration tests for APIs
- Browser tests for critical paths

## Security Standards

### Input Validation

- Never trust user input
- Validate all incoming data
- Sanitize output
- Use parameterized queries

### Authentication

- Strong password policies
- Multi-factor authentication
- Secure session management
- Proper authorization checks

## Performance Standards

### Database Queries

- Eager loading relationships
- Avoid N+1 problems
- Use database indexes
- Query optimization

### Frontend Performance

- Lazy loading components
- Image optimization
- Bundle size optimization
- Efficient reactivity

## Git Standards

### Commit Messages

- Conventional commits format
- Clear and descriptive
- Present tense
- Limited scope per commit

### Branch Naming

- feature/description
- bugfix/description
- hotfix/description
- release/version

## Documentation Standards

### Code Comments

- Explain why, not what
- Complex business logic
- API endpoints
- Configuration options

### README Files

- Installation instructions
- Usage examples
- Contributing guidelines
- License information
