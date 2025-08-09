<?php
// Simple web interface to test CSV import
session_start();

// Simulate admin session for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test CSV Import</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        input[type="file"] { width: 100%; }
        select { width: 100%; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #005a87; }
        .checkbox-group { display: flex; align-items: center; gap: 8px; }
        .result { margin-top: 20px; padding: 15px; border-radius: 4px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .credentials { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; margin-top: 10px; }
    </style>
</head>
<body>
    <h1>CSV Import Test</h1>
    
    <form id="importForm" enctype="multipart/form-data">
        <div class="form-group">
            <label for="import_file">Select CSV File:</label>
            <input type="file" id="import_file" name="import_file" accept=".csv" required>
        </div>
        
        <div class="form-group">
            <label for="import_mode">Import Mode:</label>
            <select id="import_mode" name="import_mode">
                <option value="insert_only">Insert Only (Skip existing emails)</option>
                <option value="update_existing">Update Existing (Update existing members)</option>
                <option value="mixed">Mixed (Insert new, update existing)</option>
            </select>
        </div>
        
        <div class="form-group">
            <div class="checkbox-group">
                <input type="checkbox" id="create_accounts" name="create_accounts" value="true" checked>
                <label for="create_accounts">Create login accounts for new members</label>
            </div>
        </div>
        
        <div class="form-group">
            <div class="checkbox-group">
                <input type="checkbox" id="send_credentials" name="send_credentials" value="true">
                <label for="send_credentials">Send credentials via email</label>
            </div>
        </div>
        
        <button type="submit">Import CSV</button>
    </form>
    
    <div id="result"></div>
    
    <script>
        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const fileInput = document.getElementById('import_file');
            
            if (!fileInput.files[0]) {
                alert('Please select a CSV file');
                return;
            }
            
            formData.append('import_file', fileInput.files[0]);
            formData.append('import_mode', document.getElementById('import_mode').value);
            formData.append('create_accounts', document.getElementById('create_accounts').checked ? 'true' : 'false');
            formData.append('send_credentials', document.getElementById('send_credentials').checked ? 'true' : 'false');
            
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<p>Processing...</p>';
            
            fetch('controllers/member_import_controller.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                let html = '';
                
                if (data.success) {
                    html = '<div class="result success">';
                    html += '<h3>Import Successful!</h3>';
                    html += '<p>' + data.message + '</p>';
                    
                    if (data.credentials && data.credentials.length > 0) {
                        html += '<div class="credentials">';
                        html += '<h4>New Account Credentials:</h4>';
                        data.credentials.forEach(function(cred) {
                            html += '<p><strong>' + cred.name + '</strong><br>';
                            html += 'Username: ' + cred.username + '<br>';
                            html += 'Password: ' + cred.password + '</p>';
                        });
                        html += '</div>';
                    }
                    
                    html += '</div>';
                } else {
                    html = '<div class="result error">';
                    html += '<h3>Import Failed</h3>';
                    html += '<p>' + data.message + '</p>';
                    html += '</div>';
                }
                
                resultDiv.innerHTML = html;
            })
            .catch(error => {
                console.error('Error:', error);
                resultDiv.innerHTML = '<div class="result error"><h3>Error</h3><p>An error occurred during import: ' + error.message + '</p></div>';
            });
        });
    </script>
</body>
</html>