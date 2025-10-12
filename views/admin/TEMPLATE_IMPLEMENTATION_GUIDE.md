# CSIMS Admin Template Implementation Guide

This guide shows how to use the CSIMS admin templates to create consistent, professional admin pages quickly and efficiently.

## Template Files

1. **`_template_admin_page.php`** - The main template structure
2. **`_admin_template_config.php`** - Configuration helper with pre-defined settings
3. **This guide** - Step-by-step implementation instructions

## Quick Start: Creating a New Admin Page

### Step 1: Copy the Base Template
```php
// Copy _template_admin_page.php and rename to your page (e.g., savings.php)
cp _template_admin_page.php savings.php
```

### Step 2: Configure Page-Specific Settings
```php
<?php
// At the top of your new page, add:
require_once '_admin_template_config.php';

// Get pre-configured settings for your page type
$pageConfig = AdminTemplateConfig::getPageConfig('savings'); // or 'members', 'loans', etc.

// Set page variables using the config
$pageTitle = $pageConfig['title'];
$pageDescription = $pageConfig['description'];
$pageIcon = $pageConfig['icon'];

// Or customize manually:
$pageTitle = "Custom Page Title";
$pageDescription = "Custom description";
$pageIcon = "fas fa-custom-icon";
?>
```

### Step 3: Customize Action Buttons
```php
<!-- Replace the action buttons section with: -->
<div class="flex items-center space-x-3 mt-4 md:mt-0">
    <?php echo AdminTemplateConfig::generateActionButtons($pageConfig['actions']); ?>
</div>
```

### Step 4: Add Statistics Cards (if needed)
```php
<?php if ($pageConfig['stats_enabled']): ?>
<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <?php
    $statsConfigs = AdminTemplateConfig::getStatsConfigs();
    $pageStats = isset($statsConfigs['savings']) ? $statsConfigs['savings'] : [];
    
    foreach ($pageStats as $statKey => $statConfig):
        // Get actual data from your controller/model
        $statValue = 0; // Replace with actual data
        $statChange = '+0'; // Replace with actual data
    ?>
    <div class="card card-admin">
        <div class="card-body p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="form-label text-xs mb-1" style="color: var(--lapis-lazuli);">
                        <?php echo $statConfig['label']; ?>
                    </p>
                    <p class="text-2xl font-bold" style="color: var(--text-primary);">
                        <?php echo $statValue; ?>
                    </p>
                    <p class="text-xs" style="color: var(--success);">
                        <?php echo $statChange; ?> this month
                    </p>
                </div>
                <div class="w-10 h-10 rounded-full flex items-center justify-center" 
                     style="background: linear-gradient(135deg, var(--<?php echo $statConfig['color']; ?>) 0%, var(--true-blue) 100%);">
                    <i class="<?php echo $statConfig['icon']; ?> text-white"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
```

### Step 5: Customize Filters (if needed)
```php
<?php if ($pageConfig['filters_enabled']): ?>
<!-- Enhanced Filter Section -->
<div class="card card-admin animate-fade-in mb-6">
    <div class="card-header">
        <h3 class="text-lg font-semibold flex items-center">
            <i class="fas fa-filter mr-2" style="color: var(--lapis-lazuli);"></i>
            Filter & Search
        </h3>
    </div>
    <div class="card-body p-6">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <!-- Search Field -->
            <div class="md:col-span-2">
                <label for="search" class="form-label">Search</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search" style="color: var(--text-muted);"></i>
                    </div>
                    <input type="text" class="form-control pl-10" id="search" name="search" 
                           placeholder="Search..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
            </div>
            
            <!-- Dynamic Filters -->
            <?php
            // Define which filters to show for this page
            $filters = ['status', 'date_range']; // Customize based on your needs
            echo AdminTemplateConfig::generateFilterForm($filters);
            ?>
            
            <!-- Action Buttons -->
            <div class="flex items-end space-x-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search mr-2"></i> Search
                </button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline">
                    <i class="fas fa-times mr-2"></i> Clear
                </a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
```

### Step 6: Customize the Data Table
```php
<!-- Main Content Table -->
<div class="card card-admin animate-fade-in">
    <div class="card-header flex items-center justify-between">
        <h3 class="text-lg font-semibold">Your Data Title</h3>
        <span class="badge" style="background: var(--lapis-lazuli); color: white;">
            <?php echo $totalRecords ?? 0; ?> Total Items
        </span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="dataTable">
                <thead>
                    <tr>
                        <th>Column 1</th>
                        <th>Column 2</th>
                        <th>Column 3</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data)): ?>
                        <?php foreach ($data as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['field1']); ?></td>
                            <td><?php echo htmlspecialchars($item['field2']); ?></td>
                            <td><?php echo htmlspecialchars($item['field3']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $item['status'] === 'active' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="flex space-x-2">
                                    <button class="btn btn-sm btn-outline" onclick="editItem(<?php echo $item['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline" onclick="deleteItem(<?php echo $item['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-8" style="color: var(--text-muted);">
                                <i class="fas fa-inbox text-3xl mb-2"></i>
                                <p>No data found</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
        <div class="mt-4 flex justify-between items-center">
            <p class="text-sm" style="color: var(--text-muted);">
                Showing <?php echo $pagination['start']; ?> to <?php echo $pagination['end']; ?> of <?php echo $pagination['total']; ?> results
            </p>
            <div class="flex space-x-2">
                <!-- Add pagination links -->
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
```

### Step 7: Add Page-Specific JavaScript
```php
<!-- Page-specific JavaScript -->
<script>
<?php echo AdminTemplateConfig::getCommonJavaScript(); ?>

// Your page-specific functions
function editItem(id) {
    // Implementation for editing
}

function deleteItem(id) {
    if (confirmDelete()) {
        // Implementation for deletion
    }
}

function openAddModal() {
    // Implementation for add modal
}

function exportData() {
    // Implementation for export
}
</script>
```

## Common Patterns

### Backend Controller Integration
```php
// At the top of your page, after session_start():
require_once '../../controllers/YourController.php';

$controller = new YourController();

// Handle form submissions
if ($_POST) {
    $result = $controller->handleAction($_POST);
    if ($result['success']) {
        $_SESSION['success_message'] = $result['message'];
    } else {
        $_SESSION['error_message'] = $result['message'];
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Get data for display
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    // ... other filters
];

$data = $controller->getData($filters);
$stats = $controller->getStatistics();
```

### Modal Integration
```php
<!-- Add before closing body tag -->
<!-- Add/Edit Modal -->
<div id="addModal" class="modal hidden">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Item</h3>
            <button type="button" onclick="closeModal('addModal')">&times;</button>
        </div>
        <form id="addForm" method="POST">
            <div class="modal-body">
                <!-- Form fields -->
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>
```

## Page-Specific Configurations

### Members Page
```php
$pageConfig = AdminTemplateConfig::getPageConfig('members');
$filters = ['status', 'member_type', 'date_range'];
```

### Loans Page
```php
$pageConfig = AdminTemplateConfig::getPageConfig('loans');
$filters = ['status', 'loan_type', 'amount_range', 'date_range'];
```

### Savings Page
```php
$pageConfig = AdminTemplateConfig::getPageConfig('savings');
$filters = ['status', 'amount_range', 'date_range'];
```

### Reports Page
```php
$pageConfig = AdminTemplateConfig::getPageConfig('reports');
$filters = ['date_range'];
// Disable stats for reports page
$pageConfig['stats_enabled'] = false;
```

## Best Practices

1. **Always use the configuration helper** - It ensures consistency across pages
2. **Customize only what's needed** - Start with defaults and modify as required
3. **Follow the naming conventions** - Use consistent variable names and IDs
4. **Include proper error handling** - Always validate inputs and show appropriate messages
5. **Test responsiveness** - Ensure the page works well on all screen sizes
6. **Use CSIMS color variables** - Maintain the color scheme consistency

## Color Scheme Reference

Available CSIMS color variables:
- `--lapis-lazuli` (Primary blue)
- `--true-blue` (Secondary blue)
- `--persian-orange` (Accent orange)
- `--orange-red` (Secondary orange)
- `--success` (Green)
- `--warning` (Yellow)
- `--error` (Red)
- `--text-primary` (Dark text)
- `--text-muted` (Light text)

## Need Help?

- Check existing optimized pages (dashboard.php, members.php, loans.php) for examples
- Refer to the configuration arrays in `_admin_template_config.php`
- Follow the established patterns for consistency