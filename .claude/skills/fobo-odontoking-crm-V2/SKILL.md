```markdown
# fobo-odontoking-crm-V2 Development Patterns

> Auto-generated skill from repository analysis

## Overview
This skill provides a comprehensive guide to the development patterns, coding conventions, and common workflows in the `fobo-odontoking-crm-V2` repository. The project is primarily JavaScript-based, but the backend appears to be PHP (Laravel-style), with a focus on CRM features for dental clinics. The guide covers how to add dashboard statistics, enhance data grids, manage API endpoints, handle lead attributes, update translations, configure Docker, and toggle UI components. It also documents the project's code style and testing patterns, equipping contributors to work efficiently and consistently.

## Coding Conventions

- **File Naming:**  
  Use `camelCase` for JavaScript files and directories.  
  *Example:*  
  ```
  leadDataGrid.js
  productController.js
  ```

- **Import Style:**  
  Use **relative imports** for modules within the project.  
  *Example:*  
  ```js
  import { getLeadStats } from './leadStats';
  ```

- **Export Style:**  
  Use **named exports** for functions, classes, and constants.  
  *Example:*  
  ```js
  export function calculateTotalLeads(leads) { ... }
  export const LEAD_STATUS_NEW = 'new';
  ```

- **Commit Messages:**  
  Follow the **conventional commit** style with prefixes: `feat`, `fix`, `style`, `refactor`.  
  *Example:*  
  ```
  feat: add conversion rate metric to dashboard
  fix: correct lead status display in data grid
  ```

## Workflows

### Add or Update Dashboard Statistics
**Trigger:** When introducing new business insights or updating dashboard charts.  
**Command:** `/add-dashboard-stat`

1. Update or add methods in `Dashboard.php` and relevant Reporting helpers (`Reporting/Lead.php`, `Reporting/Product.php`).
2. Update `DashboardController.php` to map new or changed endpoints.
3. Create or modify Blade view files under `views/dashboard/index/*.blade.php` to display the new statistics.
4. Update translation files if new labels are introduced.

*Example PHP (Dashboard Helper):*
```php
public function getConversionRate() {
    // Calculate and return conversion rate
}
```

### Add or Enhance Data Grid Columns
**Trigger:** When displaying new data or improving data presentation in admin data grids.  
**Command:** `/add-datagrid-column`

1. Modify the relevant DataGrid PHP class (e.g., `LeadDataGrid.php`, `ProductDataGrid.php`) to add or update columns.
2. Update value formatting or add logic for select/lookup/custom attributes.
3. Update related Blade views if UI changes are needed.
4. Optionally update translations for new column labels.

*Example PHP (DataGrid):*
```php
$this->addColumn([
    'index' => 'conversion_rate',
    'label' => trans('app.conversion_rate'),
    'type'  => 'number',
]);
```

### Add or Update API Endpoint
**Trigger:** When exposing new backend functionality or data via API.  
**Command:** `/add-api-endpoint`

1. Create or update a Controller (e.g., `ProductoController.php`) in the appropriate package.
2. Add or update route definitions (e.g., `Routes/api.php`).
3. Write or update tests (e.g., `tests/Feature/*Test.php`).
4. Update OpenAPI docs or inline documentation as needed.

*Example PHP (Route):*
```php
Route::get('products/stats', [ProductoController::class, 'stats']);
```

### Add or Update Lead Attribute or Behavior
**Trigger:** When tracking new lead data, changing attribute display, or automating lead actions.  
**Command:** `/add-lead-attribute`

1. Modify `LeadDataGrid.php` or Lead model to add/display new attributes.
2. Update lead-related Blade views (`create`, `edit`, `view`, `products`, etc.).
3. Update translations for new/changed fields.
4. Optionally add migrations for new DB columns.
5. Add or update notification logic (e.g., email on lead confirmation).

*Example PHP (Model):*
```php
protected $fillable = ['name', 'email', 'conversion_rate'];
```

### Update Spanish Translations
**Trigger:** When adding UI elements, changing terminology, or standardizing language.  
**Command:** `/update-i18n`

1. Edit `packages/Webkul/Admin/src/Resources/lang/es/app.php` and/or other lang files.
2. Update related Blade views to use new/changed translation keys.
3. Update controller or config files if translation keys are referenced in PHP.

*Example PHP (Translation):*
```php
'conversion_rate' => 'Tasa de conversión',
```

### Docker Configuration Update
**Trigger:** When improving, fixing, or adding containerization/deployment support.  
**Command:** `/update-docker`

1. Edit or add `Dockerfile` and related files (`docker/`, `.dockerignore`, `entrypoint.sh`, etc.).
2. Update or add configuration files (`php.ini`, `vhost.conf`, `nginx.conf`, `supervisord.conf`).
3. Commit lockfiles or adjust `.gitignore` as needed for build stability.
4. Document build fixes or changes for the team.

*Example Dockerfile:*
```dockerfile
FROM php:8.1-fpm
COPY . /var/www/html
...
```

### Toggle or Refactor UI Components
**Trigger:** When disabling, hiding, or restoring UI elements due to bugs, feature toggling, or refactoring.  
**Command:** `/toggle-ui-component`

1. Comment out or uncomment sections in Blade view files.
2. Update logic or conditions in the view to hide/show components.
3. Optionally update controller logic if needed.

*Example Blade:*
```blade
{{-- <x-dashboard-widget :data="$conversionRate" /> --}}
@if($showConversionRate)
    <x-dashboard-widget :data="$conversionRate" />
@endif
```

## Testing Patterns

- **Test Framework:** Unknown (likely PHPUnit for PHP, based on file patterns).
- **File Pattern:** Test files follow the `*.test.*` or `*Test.php` naming convention.
- **Location:** Tests are typically in the `tests/Feature/` directory.
- **Example Test File:**
  ```
  tests/Feature/ProductApiTest.php
  ```

## Commands

| Command               | Purpose                                                        |
|-----------------------|----------------------------------------------------------------|
| /add-dashboard-stat   | Add or update dashboard statistics and visualizations          |
| /add-datagrid-column  | Add or enhance columns in admin data grids                    |
| /add-api-endpoint     | Create or update API endpoints                                |
| /add-lead-attribute   | Add or modify lead attributes or behaviors                    |
| /update-i18n          | Update Spanish translations and related UI text               |
| /update-docker        | Add or update Docker and deployment configuration             |
| /toggle-ui-component  | Hide, show, or refactor UI components in Blade views          |
```
