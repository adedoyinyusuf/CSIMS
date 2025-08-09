<?php
require_once 'config/config.php';

// Simulate admin session for testing
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'test_admin';
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Test - CSIMS</title>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .btn-success {
            background-color: #28a745;
        }
        .btn-success:hover {
            background-color: #1e7e34;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: black;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .checkbox-group input {
            margin-right: 8px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .status {
            margin-top: 20px;
            padding: 10px;
            border-radius: 4px;
        }
        .status.success {
            background-color: #d4edda;
            color: #155724;
        }
        .status.error {
            background-color: #f8d7da;
            color: #721c24;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Member Import Test</h1>
        <p>This page tests the CSV import functionality with the updated CSP configuration.</p>
        
        <div id="status-check">
            <h2>System Status:</h2>
            <div id="jquery-status" class="status error">jQuery: Not loaded</div>
            <div id="datatables-status" class="status error">DataTables: Not loaded</div>
            <div id="session-status" class="status success">Session: Admin authenticated ✓</div>
        </div>
        
        <div style="margin: 30px 0;">
            <button id="openImportModal" class="btn btn-success">📁 Import Members</button>
            <button id="testCSV" class="btn">📄 Create Test CSV</button>
        </div>
        
        <div id="results"></div>
    </div>
    
    <!-- Import Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Import Members from CSV</h2>
            
            <form id="importForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="csv_file">Select CSV File:</label>
                    <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv" required>
                </div>
                
                <div class="form-group">
                    <label for="import_mode">Import Mode:</label>
                    <select id="import_mode" name="import_mode" class="form-control">
                        <option value="insert_only">Insert Only (Skip Duplicates)</option>
                        <option value="update_only">Update Only (Existing Records)</option>
                        <option value="insert_update">Insert & Update</option>
                    </select>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="create_accounts" name="create_accounts" value="1" checked>
                    <label for="create_accounts">Create user accounts for new members</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="send_credentials" name="send_credentials" value="1">
                    <label for="send_credentials">Send login credentials via email</label>
                </div>
                
                <button type="submit" class="btn btn-success">Import Members</button>
            </form>
            
            <div id="importProgress" style="display: none;">
                <p>Processing import...</p>
            </div>
        </div>
    </div>
    
    <script>
        // Check jQuery and DataTables loading
        if (typeof jQuery !== 'undefined') {
            document.getElementById('jquery-status').className = 'status success';
            document.getElementById('jquery-status').textContent = 'jQuery: Loaded successfully ✓';
            
            if (typeof jQuery.fn.DataTable !== 'undefined') {
                document.getElementById('datatables-status').className = 'status success';
                document.getElementById('datatables-status').textContent = 'DataTables: Loaded successfully ✓';
            }
        }
        
        // Modal functionality
        const modal = document.getElementById('importModal');
        const openBtn = document.getElementById('openImportModal');
        const closeBtn = document.querySelector('.close');
        
        openBtn.onclick = function() {
            modal.style.display = 'block';
        }
        
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // Create test CSV
        document.getElementById('testCSV').onclick = function() {
            const csvContent = `first_name,last_name,email,phone,department,position,ippis_number
John,Doe,john.doe@example.com,08012345678,IT,Software Engineer,12345678
Jane,Smith,jane.smith@example.com,08087654321,HR,HR Manager,87654321
Bob,Johnson,bob.johnson@example.com,08011111111,Finance,Accountant,11111111`;
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'test_members.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        // Handle form submission
        document.getElementById('importForm').onsubmit = function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const fileInput = document.getElementById('csv_file');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a CSV file');
                return;
            }
            
            formData.append('import_file', file);
            formData.append('import_mode', document.getElementById('import_mode').value);
            formData.append('create_accounts', document.getElementById('create_accounts').checked ? '1' : '0');
            formData.append('send_credentials', document.getElementById('send_credentials').checked ? '1' : '0');
            
            // Show progress
            document.getElementById('importProgress').style.display = 'block';
            
            // Send AJAX request
            fetch('controllers/member_import_controller.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('importProgress').style.display = 'none';
                modal.style.display = 'none';
                
                const resultsDiv = document.getElementById('results');
                
                if (data.success) {
                    resultsDiv.innerHTML = `
                        <div class="alert alert-success">
                            <h3>Import Successful!</h3>
                            <p><strong>Processed:</strong> ${data.processed || 0} records</p>
                            <p><strong>Inserted:</strong> ${data.inserted || 0} new members</p>
                            <p><strong>Updated:</strong> ${data.updated || 0} existing members</p>
                            <p><strong>Skipped:</strong> ${data.skipped || 0} duplicate records</p>
                            ${data.errors && data.errors.length > 0 ? '<p><strong>Errors:</strong> ' + data.errors.join(', ') + '</p>' : ''}
                        </div>
                    `;
                    
                    if (data.new_accounts && data.new_accounts.length > 0) {
                        let accountsTable = '<h3>New User Accounts Created:</h3><table><tr><th>Name</th><th>Username</th><th>Password</th></tr>';
                        data.new_accounts.forEach(account => {
                            accountsTable += `<tr><td>${account.name}</td><td>${account.username}</td><td>${account.password}</td></tr>`;
                        });
                        accountsTable += '</table>';
                        resultsDiv.innerHTML += accountsTable;
                    }
                } else {
                    resultsDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <h3>Import Failed</h3>
                            <p><strong>Error:</strong> ${data.message || 'Unknown error occurred'}</p>
                            ${data.errors && data.errors.length > 0 ? '<p><strong>Details:</strong> ' + data.errors.join(', ') + '</p>' : ''}
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('importProgress').style.display = 'none';
                modal.style.display = 'none';
                
                document.getElementById('results').innerHTML = `
                    <div class="alert alert-danger">
                        <h3>Network Error</h3>
                        <p>Failed to connect to the server. Please check your connection and try again.</p>
                        <p><strong>Error:</strong> ${error.message}</p>
                    </div>
                `;
            });
        }
    </script>
</body>
</html>