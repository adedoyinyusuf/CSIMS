<?php
require_once 'config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSP Test - CSIMS</title>
    
    <!-- Test DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    
    <!-- Test jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Test DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .test-result {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
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
    <h1>Content Security Policy Test</h1>
    <p>This page tests if external CDN resources can load properly with the updated CSP configuration.</p>
    
    <div id="test-results">
        <h2>Test Results:</h2>
        <div id="jquery-test" class="test-result error">jQuery: Not loaded</div>
        <div id="datatables-test" class="test-result error">DataTables: Not loaded</div>
        <div id="css-test" class="test-result error">DataTables CSS: Not loaded</div>
    </div>
    
    <h2>Sample Table for DataTables Test:</h2>
    <table id="sample-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Position</th>
                <th>Office</th>
                <th>Age</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>John Doe</td>
                <td>Software Engineer</td>
                <td>New York</td>
                <td>28</td>
            </tr>
            <tr>
                <td>Jane Smith</td>
                <td>Project Manager</td>
                <td>London</td>
                <td>32</td>
            </tr>
            <tr>
                <td>Bob Johnson</td>
                <td>Designer</td>
                <td>San Francisco</td>
                <td>26</td>
            </tr>
        </tbody>
    </table>
    
    <script>
        // Test jQuery loading
        if (typeof jQuery !== 'undefined') {
            document.getElementById('jquery-test').className = 'test-result success';
            document.getElementById('jquery-test').textContent = 'jQuery: Loaded successfully ✓';
            
            // Test DataTables loading
            if (typeof jQuery.fn.DataTable !== 'undefined') {
                document.getElementById('datatables-test').className = 'test-result success';
                document.getElementById('datatables-test').textContent = 'DataTables: Loaded successfully ✓';
                
                // Initialize DataTable
                try {
                    jQuery('#sample-table').DataTable({
                        pageLength: 5,
                        searching: true,
                        ordering: true
                    });
                } catch (e) {
                    console.error('DataTable initialization error:', e);
                }
            } else {
                document.getElementById('datatables-test').textContent = 'DataTables: jQuery loaded but DataTables not available';
            }
        } else {
            document.getElementById('jquery-test').textContent = 'jQuery: Failed to load - Check CSP configuration';
        }
        
        // Test CSS loading by checking if DataTables styles are applied
        setTimeout(function() {
            var table = document.getElementById('sample-table');
            var computedStyle = window.getComputedStyle(table);
            
            // Check if DataTables CSS is applied (DataTables adds specific styling)
            if (table.classList.contains('dataTable') || 
                computedStyle.borderCollapse === 'collapse') {
                document.getElementById('css-test').className = 'test-result success';
                document.getElementById('css-test').textContent = 'DataTables CSS: Loaded successfully ✓';
            } else {
                document.getElementById('css-test').textContent = 'DataTables CSS: Failed to load - Check CSP configuration';
            }
        }, 1000);
    </script>
</body>
</html>