<?php
// Shared System Settings Form renderer
// Expects:
// - $system_settings: array grouped by category with entries: setting_key, setting_value, setting_type, description
// - $settings_action (optional): action string for POST, defaults to 'update_settings'
// - $settings_submit_label (optional): submit button label, defaults to 'Save Settings'

$action = isset($settings_action) ? $settings_action : 'update_settings';
$submitLabel = isset($settings_submit_label) ? $settings_submit_label : 'Save Settings';
?>

<form method="POST" action="">
    <input type="hidden" name="action" value="<?php echo htmlspecialchars($action); ?>">

    <div class="space-y-8">
        <?php foreach ($system_settings as $category => $settings): ?>
            <div class="border border-gray-200 rounded-lg p-6">
                <h4 class="text-lg font-semibold text-gray-900 mb-4 capitalize">
                    <?php echo htmlspecialchars(str_replace('_', ' ', $category)); ?> Settings
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($settings as $setting): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $setting['setting_key']))); ?>
                                <?php if (!empty($setting['description'])): ?>
                                    <span class="text-gray-500 font-normal">- <?php echo htmlspecialchars($setting['description']); ?></span>
                                <?php endif; ?>
                            </label>
                            <?php if ($setting['setting_type'] === 'boolean'): ?>
                                <select name="settings[<?php echo htmlspecialchars($category); ?>][<?php echo htmlspecialchars($setting['setting_key']); ?>]" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                    <option value="true" <?php echo ($setting['setting_value'] ? 'selected' : ''); ?>>Yes</option>
                                    <option value="false" <?php echo (!$setting['setting_value'] ? 'selected' : ''); ?>>No</option>
                                </select>
                            <?php elseif ($setting['setting_type'] === 'number'): ?>
                                <input type="number" 
                                       name="settings[<?php echo htmlspecialchars($category); ?>][<?php echo htmlspecialchars($setting['setting_key']); ?>]" 
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <?php else: ?>
                                <input type="text" 
                                       name="settings[<?php echo htmlspecialchars($category); ?>][<?php echo htmlspecialchars($setting['setting_key']); ?>]" 
                                       value="<?php echo htmlspecialchars(is_array($setting['setting_value']) ? json_encode($setting['setting_value']) : $setting['setting_value']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-6 flex justify-end">
        <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
            <i class="fas fa-save mr-2"></i> <?php echo htmlspecialchars($submitLabel); ?>
        </button>
    </div>
</form>