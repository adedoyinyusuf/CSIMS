<?php
// Simple test page for import modal without authentication
require_once 'config/config.php';

// Simulate admin session for testing
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'test_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Import Modal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-8">
        <h1 class="text-2xl font-bold mb-4">Test Import Modal</h1>
        <button onclick="openImportModal()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
            <i class="fas fa-upload mr-2"></i>Test Import
        </button>
        
        <div id="result" class="mt-4 p-4 bg-white rounded shadow hidden">
            <h3 class="font-bold mb-2">Import Result:</h3>
            <pre id="resultContent"></pre>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Import Members</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeImportModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="importForm" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label for="importFile" class="block text-sm font-medium text-gray-700 mb-2">Select CSV File</label>
                        <input type="file" id="importFile" name="import_file" accept=".csv" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                        <p class="text-xs text-gray-500 mt-1">Only CSV files are supported. Maximum file size: 10MB</p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="importMode" class="block text-sm font-medium text-gray-700 mb-2">Import Mode</label>
                        <select id="importMode" name="import_mode" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="insert_only">Insert Only (Skip existing emails)</option>
                            <option value="update_existing">Update Existing (Update existing members)</option>
                            <option value="mixed">Mixed (Insert new, update existing)</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <div class="flex items-center">
                            <input type="checkbox" id="createAccounts" name="create_accounts" value="true" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                            <label for="createAccounts" class="ml-2 block text-sm text-gray-700">Create login accounts for new members</label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Generates username and password for new members</p>
                    </div>
                    
                    <div class="mb-4" id="credentialsOptions" style="display: none;">
                        <div class="flex items-center">
                            <input type="checkbox" id="sendCredentials" name="send_credentials" value="true" class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded">
                            <label for="sendCredentials" class="ml-2 block text-sm text-gray-700">Send credentials via email</label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Email login details to new members</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">CSV Format Requirements:</label>
                        <div class="text-xs text-gray-600 bg-gray-50 p-3 rounded">
                            <p class="font-medium mb-1">Required columns:</p>
                            <p>first_name, last_name, email</p>
                            <p class="font-medium mb-1 mt-2">Optional columns:</p>
                            <p>phone, gender, date_of_birth, address, membership_type_id, ippis_no, username, password</p>
                            <p class="mt-2"><strong>Note:</strong> First row should contain column headers</p>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeImportModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                            <i class="fas fa-upload mr-2"></i>Import
                        </button>
                    </div>
                </form>
                
                <div id="importProgress" class="hidden mt-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-3"></div>
                            <span class="text-sm text-blue-800">Processing import...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Import Modal Functions
        function openImportModal() {
            document.getElementById('importModal').classList.remove('hidden');
        }
        
        function closeImportModal() {
            document.getElementById('importModal').classList.add('hidden');
            document.getElementById('importForm').reset();
            document.getElementById('importProgress').classList.add('hidden');
        }
        
        // Show/hide credentials options based on create accounts checkbox
        document.getElementById('createAccounts').addEventListener('change', function() {
            const credentialsOptions = document.getElementById('credentialsOptions');
            if (this.checked) {
                credentialsOptions.style.display = 'block';
            } else {
                credentialsOptions.style.display = 'none';
                document.getElementById('sendCredentials').checked = false;
            }
        });
        
        // Handle Import Form Submission
        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('importFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a CSV file to import.');
                return;
            }
            
            // Show progress indicator
            document.getElementById('importProgress').classList.remove('hidden');
            
            // Create FormData object
            const formData = new FormData();
            formData.append('import_file', file);
            formData.append('import_mode', document.getElementById('importMode').value);
            formData.append('create_accounts', document.getElementById('createAccounts').checked ? 'true' : 'false');
            formData.append('send_credentials', document.getElementById('sendCredentials').checked ? 'true' : 'false');
            
            // Send AJAX request
            fetch('<?php echo BASE_URL; ?>/controllers/member_import_controller.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                document.getElementById('importProgress').classList.add('hidden');
                
                // Show raw response for debugging
                document.getElementById('result').classList.remove('hidden');
                document.getElementById('resultContent').textContent = text;
                
                try {
                    const data = JSON.parse(text);
                    
                    if (data.success) {
                        let message = data.message;
                        
                        // Show credentials if not sent via email
                        if (data.credentials && data.credentials.length > 0) {
                            message += '\n\nNew Account Credentials:';
                            data.credentials.forEach(function(cred) {
                                message += '\n' + cred.name + ' - Username: ' + cred.username + ', Password: ' + cred.password;
                            });
                            message += '\n\nPlease save these credentials and share them securely with the members.';
                        }
                        
                        alert('Import completed successfully! ' + message);
                        closeImportModal();
                    } else {
                        alert('Import failed: ' + data.message);
                    }
                } catch (error) {
                    console.error('JSON Parse Error:', error);
                    alert('Response received but could not parse JSON. Check the result section below.');
                }
            })
            .catch(error => {
                document.getElementById('importProgress').classList.add('hidden');
                console.error('Error:', error);
                alert('An error occurred during import. Please try again.');
            });
        });
    </script>
</body>
</html>