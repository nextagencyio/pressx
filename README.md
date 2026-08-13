# PressX - Headless WordPress Setup

> [!WARNING]
> **This repository is deprecated.**
>
> The headless WordPress + Next.js PressX stack is no longer maintained.
> Use the current PressX product instead:
>
> - WordPress (Timber / Bedrock): [`nextagencyio/pressx-wp`](https://github.com/nextagencyio/pressx-wp)
> - Twing frontend workbench: [`nextagencyio/pressx-frontend`](https://github.com/nextagencyio/pressx-frontend)
>
> Do not start new engagements from this repo.


PressX is a modern headless WordPress setup that combines the power of WordPress as a backend CMS with Next.js for the frontend, all containerized using DDEV for consistent development environments.

## 🚀 Quick Start

1. **Prerequisites**
   - [DDEV](https://ddev.readthedocs.io/en/stable/)
   - [Docker](https://www.docker.com/)
   - [Composer](https://getcomposer.org/)
   - [Node.js](https://nodejs.org/) (for Next.js frontend)

2. **Installation**
   ```bash
   # Clone the repository
   git clone [your-repo-url]
   cd pressx

   # Start DDEV
   ddev start

   # Install WordPress and dependencies
   ddev install

   # Or install with preview mode enabled
   ddev install --preview

   # Install Next.js dependencies
   cd nextjs
   npm install
   ```

   The `ddev install` command will:
   - Set up the WordPress installation
   - Install and activate required plugins (pressx-core, classic-editor, wp-graphql)
   - Configure permalink structure
   - Create sample content (landing pages, blog posts)
   - Set up navigation menus
   - Generate admin credentials

   Using the `--preview` flag will additionally:
   - Configure the Next.js environment for preview mode
   - Set up the necessary environment variables
   - Enable real-time content previewing from WordPress to Next.js

   You can also create content manually using the WP CLI commands:
   ```bash
   # Create all standard content
   ddev wp pressx create-pages && \
   ddev wp pressx create-home && \
   ddev wp pressx create-pricing && \
   ddev wp pressx create-features && \
   ddev wp pressx create-resources && \
   ddev wp pressx create-get-started && \
   ddev wp pressx create-articles && \
   ddev wp pressx create-contact && \
   ddev wp pressx create-menu && \
   ddev wp pressx create-footer-menu
   ```

3. **Running the Application**
   ```bash
   # Start WordPress backend
   ddev start

   # Start Next.js frontend (in a separate terminal)
   cd nextjs
   npm run dev

   # Or use the DDEV command if you installed with preview mode
   ddev nextjs
   ```

## 🏗️ Project Structure

```
pressx/
├── public_html/              # WordPress installation
│   └── wp-content/
│       └── plugins/
│           └── pressx-core/  # Custom plugin for headless functionality
├── nextjs/                   # Next.js frontend application
│   └── src/
│       ├── components/       # Reusable UI components
│       │   └── sections/     # Page section components
│       ├── pages/            # Next.js pages
│       └── lib/              # Utility functions and API clients
├── drupal-config/            # Drupal configuration reference files
├── scripts/                  # Helper scripts for content creation
├── vendor/                   # Composer dependencies
└── .ddev/                    # DDEV configuration
```

## 🔧 Development

### WordPress Backend

- **Local URL**: https://pressx.ddev.site
- **Admin URL**: https://pressx.ddev.site/wp-admin
- **GraphQL Endpoint**: https://pressx.ddev.site/graphql

The WordPress backend uses several key components:
- Carbon Fields for custom fields
- WPGraphQL for the GraphQL API
- Custom post types and fields defined in the pressx-core plugin
- Classic Editor for content management

### Next.js Frontend

- **Development URL**: http://pressx.ddev.site:3333 or http://localhost:3333
- **Development**: `cd nextjs && npm run dev`
- **Build**: `cd nextjs && npm run build`
- **Start**: `cd nextjs && npm start`

### Preview Mode

PressX supports a preview mode that allows you to see content changes in real-time before publishing:

1. **Enabling Preview Mode**:
   - Install with preview mode: `ddev install --preview`
   - Or toggle it on/off anytime: `ddev toggle-preview on` or `ddev toggle-preview off`
   - Or manually configure by setting `NEXT_PUBLIC_PREVIEW_MODE=true` in `nextjs/.env.local`

2. **Using Preview Mode**:
   - Edit content in WordPress
   - Click the "Preview" button to see changes in the Next.js frontend
   - Preview URLs follow the pattern: `http://localhost:3333/preview/[id]`

3. **Admin Bar**:
   - When in preview mode, an admin bar appears at the top of all pages
   - Shows the current page type and post ID for easy reference
   - Provides quick links to edit the current content in WordPress
   - Includes a home button for easy navigation
   - Can be hidden/shown with a toggle button
   - Responsive design works on all device sizes
   - Automatically adjusts the page layout to prevent overlap with content

4. **Preview Mode Environment Variables**:
   ```
   NEXT_PUBLIC_PREVIEW_MODE=true
   WORDPRESS_PREVIEW_SECRET=pressx_preview_secret
   WORDPRESS_PREVIEW_USERNAME=admin
   WORDPRESS_PREVIEW_PASSWORD=your_password_here
   ```

   > **IMPORTANT**: You must set `WORDPRESS_PREVIEW_USERNAME` and `WORDPRESS_PREVIEW_PASSWORD` to valid WordPress credentials with appropriate permissions. These credentials are used to authenticate with the WordPress GraphQL API to access unpublished content. Never commit these credentials to version control.

5. **JWT Authentication**:
   - The preview mode uses JWT authentication to securely access unpublished content
   - JWT tokens are automatically retrieved and stored in cookies
   - The GraphQL client uses these tokens when making requests to the WordPress API
   - For security, tokens expire after a short period and are automatically refreshed

## 🛠️ Key Features

1. **Headless WordPress**
   - Decoupled architecture
   - GraphQL API using WPGraphQL
   - Custom post types and fields

2. **Modern Frontend**
   - Next.js for server-side rendering
   - TypeScript support
   - Component-based architecture
   - Tailwind CSS for styling

3. **Development Environment**
   - DDEV for containerization
   - Automated setup process
   - Consistent development environment

4. **Content Sections**
   - Flexible page builder with multiple section types
   - Component-based design for easy customization
   - Responsive layouts for all devices

5. **Sample Content**
   - Pre-configured landing pages (Home, Features, Pricing, Resources, Get Started, Contact)
   - Sample blog posts
   - Navigation menus (Primary and Footer)

6. **Preview Mode**
   - Real-time content previewing
   - Secure preview links
   - Seamless WordPress to Next.js integration

## 📦 Available Section Types

PressX includes a variety of section types for building flexible landing pages:

1. **Hero** - Feature prominent content with various layout options
2. **Text** - Simple text sections with optional links
3. **Accordion** - Expandable content sections for FAQs or detailed information
4. **Card Group** - Display content in card format with various card types
5. **Carousel** - Scrollable content with images and text
6. **Embed** - Embed external content with support for rich media scripts
7. **Gallery** - Display multiple images in a grid layout
8. **Logo Collection** - Showcase partner or client logos
9. **Media** - Display images or videos with optional captions
10. **Newsletter** - Email signup form
11. **Pricing** - Display pricing options in a structured format
12. **Quote** - Highlight testimonials or important quotes
13. **Side by Side** - Two-column layout with text and media
14. **Recent Posts** - Display recent blog posts

## 🔄 Working with Section Types

### Embed Section

The Embed section allows you to include external content like videos, maps, or other third-party widgets. Recent updates have improved this section to support full HTML/script embeds rather than just URLs.

**Key Features:**
- Support for rich script embeds (iframes, JavaScript widgets, etc.)
- Optional title and caption
- Configurable maximum width
- Responsive design

**Example Usage:**
```php
[
  '_type' => 'embed',
  'title' => 'Watch Our Tutorial',
  'script' => '<iframe src="https://www.youtube.com/embed/VIDEO_ID" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="width:100%; aspect-ratio:16/9;"></iframe>',
  'caption' => 'Learn how to get started with PressX in this quick tutorial.',
  'max_width' => '800px',
]
```

## 📦 Dependencies

### WordPress (Composer)
- `htmlburger/carbon-fields`: Custom fields framework
- `wp-graphql`: GraphQL API for WordPress
- `classic-editor`: Traditional WordPress editing experience
- Other WordPress plugins managed via Composer

### Frontend (npm)
- Next.js
- TypeScript
- Tailwind CSS
- Shadcn UI components
- Other frontend dependencies managed via npm

## 🔄 Common Workflows

### Adding Custom Fields
1. Define fields in `pressx-core/pressx-core.php`
2. Update GraphQL schema if needed
3. Update TypeScript types in the frontend

### Adding New Post Types
1. Register post type in `pressx-core/pressx-core.php`
2. Add custom fields if needed
3. Update GraphQL schema
4. Create corresponding frontend components

### Creating Content
1. Use the WordPress admin interface to create content
2. Alternatively, use the WP CLI commands to create content:
   ```bash
   # Create a landing page
   ddev wp pressx create-landing

   # Create a contact page
   ddev wp pressx create-contact

   # Create a get started page
   ddev wp pressx create-get-started

   # Create an AI-generated landing page
   ddev wp pressx create-ai-landing "coffee shop"
   ```

   See the [PressX WP CLI Commands](#-pressx-wp-cli-commands) section for more details.

## 🛠️ PressX WP CLI Commands

PressX includes a set of WP CLI commands to help you manage and create content. These commands are available under the `wp pressx` namespace and can be run using DDEV:

### Available Commands

```bash
# Create standard pages (Privacy Policy and Terms of Use)
ddev wp pressx create-pages

# Create specific landing pages
ddev wp pressx create-home
ddev wp pressx create-pricing
ddev wp pressx create-features
ddev wp pressx create-resources
ddev wp pressx create-landing
ddev wp pressx create-get-started
ddev wp pressx create-articles
ddev wp pressx create-contact

# Create navigation menus
ddev wp pressx create-menu
ddev wp pressx create-footer-menu

# Create AI-generated landing page
ddev wp pressx create-ai-landing "coffee shop"

# Test Pexels API integration
ddev wp pressx test-pexels
ddev wp pressx test-pexels "mountain landscape" --count=6
```

### Command Options

Most commands support the following options:

- `--force` - Force recreation of content even if it already exists

### AI Landing Page Generation

The `create-ai-landing` command creates an AI-generated landing page using OpenRouter or Groq with optional Pexels image search:

1. **Configuration**:
   - Add API keys to your `wp-config.php`:
     ```php
     define('OPENROUTER_API_KEY', 'your-api-key-here');
     // or
     define('GROQ_API_KEY', 'your-api-key-here');

     // Optional: Enable Pexels image search
     define('PRESSX_USE_PEXELS_IMAGES', TRUE);
     define('PEXELS_API_KEY', 'your-pexels-api-key');

     // Optional: Set preferred AI API
     define('PRESSX_AI_API', 'openrouter'); // or 'groq'
     ```

2. **Usage**:
   ```bash
   # Generate with interactive prompt
   ddev wp pressx create-ai-landing

   # Generate with specific prompt
   ddev wp pressx create-ai-landing "coffee shop"
   ```

3. **Features**:
   - Generates 6 different section types
   - Creates appropriate content based on the prompt
   - Automatically searches for relevant images if Pexels is enabled
   - Creates a published landing page with a unique slug

### Chatbot Integration

PressX includes an AI-powered chatbot that can be integrated into your website to provide interactive assistance to your visitors:

1. **Configuration**:
   - Add API keys to your `wp-config.php`:
     ```php
     // Required for chatbot functionality
     define('OPENAI_API_KEY', 'your-openai-api-key-here');

     // Optional: Configure chatbot behavior
     define('PRESSX_CHATBOT_MODEL', 'gpt-4o'); // Default model to use
     define('PRESSX_CHATBOT_SYSTEM_PROMPT', 'You are a helpful assistant for our website.'); // Default system prompt
     define('PRESSX_CHATBOT_MAX_TOKENS', 1000); // Maximum response length
     ```

2. **Adding to Pages**:
   - The chatbot can be added to any page using the Chatbot section type
   - Configure appearance, initial messages, and behavior in the section settings
   - Customize the chatbot's knowledge base by providing specific instructions

3. **Features**:
   - Real-time AI-powered conversations with site visitors
   - Customizable appearance to match your site's design
   - Context-aware responses based on the page content
   - Optional knowledge base integration for product-specific information
   - Conversation history for returning visitors (requires user consent)
   - Mobile-responsive design

4. **Usage Example**:
   ```php
   [
     '_type' => 'chatbot',
     'title' => 'Customer Support',
     'initial_message' => 'Hello! How can I help you today?',
     'placeholder_text' => 'Type your question here...',
     'position' => 'bottom-right', // Options: bottom-right, bottom-left, centered
     'theme' => 'light', // Options: light, dark, custom
     'custom_instructions' => 'You are a helpful assistant for our coffee shop website. You can help with menu questions, store hours, and placing orders.',
   ]
   ```

### Pexels API Testing

The `test-pexels` command helps test the Pexels API integration:

```bash
# Test with default queries
ddev wp pressx test-pexels

# Test with a specific query
ddev wp pressx test-pexels "coffee shop"

# Test with a specific query and image count
ddev wp pressx test-pexels "mountain landscape" --count=6
```

### Creating All Content

To create all standard content at once:

```bash
ddev wp pressx create-pages && \
ddev wp pressx create-home && \
ddev wp pressx create-pricing && \
ddev wp pressx create-features && \
ddev wp pressx create-resources && \
ddev wp pressx create-landing && \
ddev wp pressx create-get-started && \
ddev wp pressx create-articles && \
ddev wp pressx create-contact && \
ddev wp pressx create-menu && \
ddev wp pressx create-footer-menu
```

## 🚨 Troubleshooting

### Common Issues

1. **Carbon Fields UI Not Loading**
   - Ensure Carbon Fields assets are properly copied to the plugin directory
   - Check browser console for JavaScript errors
   - Verify WordPress admin scripts are loading

2. **GraphQL Errors**
   - Verify WPGraphQL plugin is activated
   - Check field registration in the schema
   - Validate query structure
   - Test queries in the GraphQL IDE at https://pressx.ddev.site/graphql

3. **Next.js Development Server Issues**
   - Ensure Node.js version is compatible
   - Check for TypeScript errors
   - Verify GraphQL queries match the schema

4. **Preview Mode Authentication Issues**
   - If you're having trouble with preview mode authentication:
     - Run `ddev toggle-preview on` to reset the admin password and update environment variables
     - Check that the WPGraphQL JWT Authentication plugin is active
     - Verify that your `.env.local` file contains the correct credentials
     - Clear your browser cookies and try again
   - The `toggle-preview` script automatically:
     - Sets a secure password for the admin user
     - Updates the `.env.local` file with the correct credentials
     - Activates the necessary plugins for JWT authentication

5. **DDEV Configuration Issues**
   - Run `ddev describe` to check the current configuration
   - Ensure ports 80, 443, and 3333 are available
   - Check DDEV logs with `ddev logs`

## 📝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📄 License

PressX is dual-licensed:

- WordPress-specific code (plugins, themes, PHP files) is licensed under [GPL-2.0](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html) or later
- Next.js frontend code is licensed under the [MIT License](https://opensource.org/licenses/MIT)

See the [LICENSE](LICENSE) file for details.
