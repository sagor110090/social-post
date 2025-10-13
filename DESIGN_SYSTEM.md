# AutoPost AI - Design System & Style Guide

## Brand Identity

### Primary Brand Colors

- **Primary Blue**: `hsl(221, 83%, 53%)` - `#3B82F6`
- **Primary Dark**: `hsl(221, 83%, 43%)` - `#2563EB`
- **Accent Purple**: `hsl(262, 83%, 58%)` - `#8B5CF6`
- **Success Green**: `hsl(142, 76%, 36%)` - `#10B981`
- **Warning Amber**: `hsl(38, 92%, 50%)` - `#F59E0B`
- **Error Red**: `hsl(0, 84%, 60%)` - `#EF4444`

### Neutral Palette

- **Gray 50**: `hsl(0, 0%, 98%)` - `#FAFAFA`
- **Gray 100**: `hsl(0, 0%, 96%)` - `#F5F5F5`
- **Gray 200**: `hsl(0, 0%, 94%)` - `#E5E5E5`
- **Gray 300**: `hsl(0, 0%, 91%)` - `#E8E8E8`
- **Gray 400**: `hsl(0, 0%, 74%)` - `#BDBDBD`
- **Gray 500**: `hsl(0, 0%, 46%)` - `#757575`
- **Gray 600**: `hsl(0, 0%, 28%)` - `#474747`
- **Gray 700**: `hsl(0, 0%, 22%)` - `#383838`
- **Gray 800**: `hsl(0, 0%, 17%)` - `#2B2B2B`
- **Gray 900**: `hsl(0, 0%, 11%)` - `#1C1C1C`

### Social Media Platform Colors

- **Facebook**: `#1877F2`
- **Instagram**: `#E4405F`
- **LinkedIn**: `#0A66C2`
- **X/Twitter**: `#1DA1F2`
- **YouTube**: `#FF0000`
- **TikTok**: `#000000`

## Typography

### Font Family

- **Primary**: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif
- **Monospace**: 'JetBrains Mono', 'Fira Code', monospace

### Type Scale

- **Display 1**: 48px / 56px (3rem / 3.5rem) - font-light
- **Display 2**: 36px / 44px (2.25rem / 2.75rem) - font-light
- **Headline 1**: 32px / 40px (2rem / 2.5rem) - font-semibold
- **Headline 2**: 24px / 32px (1.5rem / 2rem) - font-semibold
- **Headline 3**: 20px / 28px (1.25rem / 1.75rem) - font-medium
- **Headline 4**: 18px / 24px (1.125rem / 1.5rem) - font-medium
- **Body Large**: 16px / 24px (1rem / 1.5rem) - font-normal
- **Body**: 14px / 20px (0.875rem / 1.25rem) - font-normal
- **Body Small**: 12px / 16px (0.75rem / 1rem) - font-normal
- **Caption**: 11px / 16px (0.6875rem / 1rem) - font-normal

### Font Weights

- **Light**: 300
- **Normal**: 400
- **Medium**: 500
- **Semibold**: 600
- **Bold**: 700

## Spacing System

### Base Unit: 4px (0.25rem)

- **xs**: 4px (0.25rem)
- **sm**: 8px (0.5rem)
- **md**: 16px (1rem)
- **lg**: 24px (1.5rem)
- **xl**: 32px (2rem)
- **2xl**: 48px (3rem)
- **3xl**: 64px (4rem)
- **4xl**: 96px (6rem)

## Border Radius

### Scale

- **none**: 0px
- **sm**: 2px
- **md**: 6px
- **lg**: 8px
- **xl**: 12px
- **2xl**: 16px
- **full**: 9999px

## Shadows

### Elevation Scale

- **sm**: `0 1px 2px 0 rgb(0 0 0 / 0.05)`
- **md**: `0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)`
- **lg**: `0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)`
- **xl**: `0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)`
- **2xl**: `0 25px 50px -12px rgb(0 0 0 / 0.25)`

## Components

### Buttons

#### Primary Button

- Background: Primary Blue
- Text: White
- Border Radius: md (6px)
- Padding: sm (8px) md (16px)
- Font Weight: medium (500)
- Shadow: sm
- Hover: Primary Dark with transition

#### Secondary Button

- Background: Transparent
- Text: Primary Blue
- Border: 1px solid Primary Blue
- Border Radius: md (6px)
- Padding: sm (8px) md (16px)
- Font Weight: medium (500)
- Hover: Background Primary Blue with 10% opacity

#### Ghost Button

- Background: Transparent
- Text: Gray 600
- Border: None
- Border Radius: md (6px)
- Padding: sm (8px) md (16px)
- Font Weight: medium (500)
- Hover: Background Gray 100

### Cards

#### Default Card

- Background: White
- Border: 1px solid Gray 200
- Border Radius: lg (8px)
- Shadow: sm
- Padding: lg (24px)

#### Elevated Card

- Background: White
- Border: None
- Border Radius: lg (8px)
- Shadow: md
- Padding: lg (24px)

### Form Elements

#### Input Fields

- Background: White
- Border: 1px solid Gray 300
- Border Radius: md (6px)
- Padding: sm (8px) md (16px)
- Font Size: Body (14px)
- Focus: Border Primary Blue, Ring 2px Primary Blue with 20% opacity

#### Labels

- Font Size: Body Small (12px)
- Font Weight: medium (500)
- Color: Gray 700
- Margin Bottom: xs (4px)

## Layout System

### Container Max Widths

- **sm**: 640px
- **md**: 768px
- **lg**: 1024px
- **xl**: 1280px
- **2xl**: 1536px

### Grid System

- **12-column grid**
- **Gap**: md (16px) default, lg (24px) for larger screens
- **Responsive breakpoints**: sm (640px), md (768px), lg (1024px), xl (1280px), 2xl (1536px)

### Sidebar Layout

- **Width**: 280px (collapsed: 64px)
- **Background**: Gray 50
- **Border**: 1px solid Gray 200
- **Padding**: sm (8px)

## Animation & Transitions

### Duration

- **fast**: 150ms
- **normal**: 300ms
- **slow**: 500ms

### Easing

- **ease-in**: `cubic-bezier(0.4, 0, 1, 1)`
- **ease-out**: `cubic-bezier(0, 0, 0.2, 1)`
- **ease-in-out**: `cubic-bezier(0.4, 0, 0.2, 1)`

### Common Transitions

- **Hover**: `all 150ms ease-in-out`
- **Focus**: `all 150ms ease-in-out`
- **Modal**: `all 300ms ease-in-out`
- **Dropdown**: `all 200ms ease-in-out`

## Iconography

### Icon Library

- **Primary**: Lucide Icons
- **Size Scale**: xs (12px), sm (16px), md (20px), lg (24px), xl (28px), 2xl (32px)

### Social Media Icons

- Use platform-specific colors
- Maintain consistent sizing
- Include hover states with opacity changes

## Accessibility (WCAG 2.1 AA)

### Color Contrast Ratios

- **Normal Text**: 4.5:1 minimum
- **Large Text**: 3:1 minimum
- **Non-text Elements**: 3:1 minimum

### Focus States

- **Outline**: 2px solid Primary Blue
- **Offset**: 2px
- **Always visible** on interactive elements

### Screen Reader Support

- **ARIA labels** for all interactive elements
- **Semantic HTML5** elements
- **Skip to main content** link
- **Alt text** for all meaningful images

### Keyboard Navigation

- **Tab order** logical and predictable
- **Focus trap** in modals
- **Escape key** closes modals/dropdowns

## Responsive Design

### Breakpoints

- **Mobile**: 320px - 639px
- **Tablet**: 640px - 1023px
- **Desktop**: 1024px - 1279px
- **Large Desktop**: 1280px+

### Mobile-First Approach

- **Base styles** for mobile (320px+)
- **Progressive enhancement** for larger screens
- **Touch targets** minimum 44px
- **Readable text** without zooming

## Dark Mode

### Color Adaptations

- **Background**: Gray 900
- **Surface**: Gray 800
- **Text Primary**: Gray 100
- **Text Secondary**: Gray 400
- **Border**: Gray 700
- **Primary Blue**: Adjusted for better contrast

### Implementation

- **CSS custom properties** for easy switching
- **System preference** detection
- **Manual toggle** with persistence
- **Smooth transitions** between modes

## Component States

### Interactive Elements

- **Default**: Base styling
- **Hover**: Subtle visual feedback
- **Focus**: Clear focus indicator
- **Active**: Pressed state
- **Disabled**: Reduced opacity, no interaction

### Loading States

- **Skeleton loaders** for content
- **Spinners** for actions
- **Progress bars** for processes
- **Shimmer effects** for images

## Error Handling

### Validation States

- **Error**: Red border, error icon, error message
- **Warning**: Yellow border, warning icon
- **Success**: Green border, success icon
- **Info**: Blue border, info icon

### Messaging

- **Toast notifications** for temporary messages
- **Inline errors** for form validation
- **Modal dialogs** for critical errors
- **Empty states** with helpful guidance

## Performance Guidelines

### Image Optimization

- **WebP format** with fallbacks
- **Responsive images** with srcset
- **Lazy loading** for below-fold images
- **Compression** for file size reduction

### Animation Performance

- **Transform** and **opacity** for 60fps
- **Will-change** property sparingly
- **Reduced motion** for accessibility
- **Hardware acceleration** where beneficial

## Brand Voice & Tone

### Personality

- **Professional** yet approachable
- **Helpful** and supportive
- **Efficient** and modern
- **Trustworthy** and reliable

### Language Guidelines

- **Clear** and concise
- **Action-oriented** CTAs
- **Inclusive** and accessible
- **Consistent** terminology

This design system serves as the foundation for all AutoPost AI interfaces, ensuring consistency, accessibility, and a professional user experience across all platforms and devices.
