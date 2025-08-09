# CSIMS API Documentation

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Error Handling](#error-handling)
4. [Rate Limiting](#rate-limiting)
5. [Member Management API](#member-management-api)
6. [Financial Management API](#financial-management-api)
7. [Communication API](#communication-api)
8. [Reporting API](#reporting-api)
9. [Admin API](#admin-api)
10. [Response Formats](#response-formats)
11. [Code Examples](#code-examples)

## Overview

The CSIMS API provides programmatic access to the Cooperative Society Information Management System. This RESTful API allows you to manage members, financial transactions, communications, and generate reports.

### Base URL
```
http://localhost/CSIMS/api/
```

### API Version
Current version: v1.0

### Content Type
All requests should use `application/json` content type unless specified otherwise.

### HTTP Methods
- `GET` - Retrieve data
- `POST` - Create new resources
- `PUT` - Update existing resources
- `DELETE` - Remove resources

## Authentication

### Session-Based Authentication
The API uses session-based authentication. Users must log in through the web interface or API login endpoint.

#### Login Endpoint
```http
POST /auth/login
Content-Type: application/json

{
    "username": "admin",
    "password": "password123",
    "user_type": "admin" // or "member"
}
```

#### Response
```json
{
    "status": "success",
    "message": "Login successful",
    "user": {
        "id": 1,
        "username": "admin",
        "role": "Admin",
        "last_login": "2024-01-15 10:30:00"
    },
    "session_id": "abc123def456"
}
```

#### Logout Endpoint
```http
POST /auth/logout
```

### Authorization Headers
For API requests, include the session cookie or authorization header:
```http
Authorization: Bearer {session_token}
```

## Error Handling

### Standard Error Response
```json
{
    "status": "error",
    "error_code": "VALIDATION_ERROR",
    "message": "Invalid input data",
    "details": {
        "field": "email",
        "issue": "Invalid email format"
    },
    "timestamp": "2024-01-15T10:30:00Z"
}
```

### HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error

### Common Error Codes
- `AUTH_REQUIRED` - Authentication required
- `INVALID_CREDENTIALS` - Invalid login credentials
- `VALIDATION_ERROR` - Input validation failed
- `RESOURCE_NOT_FOUND` - Requested resource not found
- `PERMISSION_DENIED` - Insufficient permissions
- `DUPLICATE_ENTRY` - Resource already exists

## Rate Limiting

### Limits
- 100 requests per minute per IP address
- 1000 requests per hour per authenticated user

### Headers
```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1642248600
```

## Member Management API

### List Members
```http
GET /api/members
```

#### Query Parameters
- `page` (integer) - Page number (default: 1)
- `limit` (integer) - Items per page (default: 10, max: 100)
- `search` (string) - Search term
- `status` (string) - Filter by status (Active, Inactive, Suspended, Expired)
- `membership_type` (integer) - Filter by membership type ID

#### Response
```json
{
    "status": "success",
    "data": {
        "members": [
            {
                "member_id": 1,
                "ippis_no": "123456",
                "first_name": "John",
                "last_name": "Doe",
                "email": "john.doe@example.com",
                "phone": "+1234567890",
                "status": "Active",
                "join_date": "2024-01-01",
                "membership_type": "Regular"
            }
        ],
        "pagination": {
            "current_page": 1,
            "total_pages": 5,
            "total_items": 50,
            "items_per_page": 10
        }
    }
}
```

### Get Member Details
```http
GET /api/members/{member_id}
```

#### Response
```json
{
    "status": "success",
    "data": {
        "member_id": 1,
        "ippis_no": "123456",
        "username": "johndoe",
        "first_name": "John",
        "last_name": "Doe",
        "dob": "1990-05-15",
        "gender": "Male",
        "address": "123 Main St, City",
        "phone": "+1234567890",
        "email": "john.doe@example.com",
        "occupation": "Engineer",
        "membership_type_id": 1,
        "join_date": "2024-01-01",
        "expiry_date": "2024-12-31",
        "status": "Active",
        "last_login": "2024-01-15 09:30:00",
        "created_at": "2024-01-01 10:00:00"
    }
}
```

### Create Member
```http
POST /api/members
Content-Type: application/json

{
    "ippis_no": "123456",
    "username": "johndoe",
    "password": "securepassword",
    "first_name": "John",
    "last_name": "Doe",
    "dob": "1990-05-15",
    "gender": "Male",
    "address": "123 Main St, City",
    "phone": "+1234567890",
    "email": "john.doe@example.com",
    "occupation": "Engineer",
    "membership_type_id": 1
}
```

### Update Member
```http
PUT /api/members/{member_id}
Content-Type: application/json

{
    "first_name": "John",
    "last_name": "Smith",
    "phone": "+1234567891",
    "address": "456 Oak Ave, City"
}
```

### Delete Member
```http
DELETE /api/members/{member_id}
```

## Financial Management API

### Contributions

#### List Contributions
```http
GET /api/contributions
```

#### Query Parameters
- `member_id` (integer) - Filter by member
- `start_date` (date) - Start date filter (YYYY-MM-DD)
- `end_date` (date) - End date filter (YYYY-MM-DD)
- `type` (string) - Contribution type (Dues, Investment, Other)

#### Response
```json
{
    "status": "success",
    "data": {
        "contributions": [
            {
                "contribution_id": 1,
                "member_id": 1,
                "member_name": "John Doe",
                "amount": 1000.00,
                "contribution_date": "2024-01-15",
                "contribution_type": "Dues",
                "description": "Monthly dues payment",
                "received_by": "Admin User",
                "created_at": "2024-01-15 10:30:00"
            }
        ],
        "summary": {
            "total_amount": 5000.00,
            "total_count": 5
        }
    }
}
```

#### Add Contribution
```http
POST /api/contributions
Content-Type: application/json

{
    "member_id": 1,
    "amount": 1000.00,
    "contribution_date": "2024-01-15",
    "contribution_type": "Dues",
    "description": "Monthly dues payment"
}
```

### Loans

#### List Loans
```http
GET /api/loans
```

#### Query Parameters
- `member_id` (integer) - Filter by member
- `status` (string) - Filter by status (Pending, Approved, Rejected, Disbursed, Paid)
- `start_date` (date) - Application start date
- `end_date` (date) - Application end date

#### Response
```json
{
    "status": "success",
    "data": {
        "loans": [
            {
                "loan_id": 1,
                "member_id": 1,
                "member_name": "John Doe",
                "amount": 50000.00,
                "interest_rate": 5.5,
                "term": 12,
                "purpose": "Business expansion",
                "application_date": "2024-01-10",
                "approval_date": "2024-01-12",
                "status": "Approved",
                "approved_by": "Admin User"
            }
        ]
    }
}
```

#### Create Loan Application
```http
POST /api/loans
Content-Type: application/json

{
    "member_id": 1,
    "amount": 50000.00,
    "interest_rate": 5.5,
    "term": 12,
    "purpose": "Business expansion"
}
```

#### Update Loan Status
```http
PUT /api/loans/{loan_id}/status
Content-Type: application/json

{
    "status": "Approved",
    "notes": "Loan approved after review"
}
```

## Communication API

### Messages

#### List Messages
```http
GET /api/messages
```

#### Query Parameters
- `type` (string) - Filter by type (sent, received, all)
- `is_read` (boolean) - Filter by read status
- `sender_type` (string) - Filter by sender type (Member, Admin)

#### Response
```json
{
    "status": "success",
    "data": {
        "messages": [
            {
                "message_id": 1,
                "sender_type": "Admin",
                "sender_id": 1,
                "sender_name": "Admin User",
                "recipient_type": "Member",
                "recipient_id": 1,
                "recipient_name": "John Doe",
                "subject": "Welcome to CSIMS",
                "message": "Welcome to our cooperative society...",
                "is_read": false,
                "created_at": "2024-01-15 10:30:00"
            }
        ]
    }
}
```

#### Send Message
```http
POST /api/messages
Content-Type: application/json

{
    "recipient_type": "Member",
    "recipient_id": 1,
    "subject": "Important Notice",
    "message": "This is an important message..."
}
```

#### Mark Message as Read
```http
PUT /api/messages/{message_id}/read
```

### Notifications

#### List Notifications
```http
GET /api/notifications
```

#### Response
```json
{
    "status": "success",
    "data": {
        "notifications": [
            {
                "notification_id": 1,
                "title": "System Maintenance",
                "message": "System will be down for maintenance...",
                "recipient_type": "All",
                "notification_type": "General",
                "is_read": false,
                "created_by": "Admin User",
                "created_at": "2024-01-15 10:30:00"
            }
        ]
    }
}
```

#### Create Notification
```http
POST /api/notifications
Content-Type: application/json

{
    "title": "System Maintenance",
    "message": "System will be down for maintenance on Sunday...",
    "recipient_type": "All",
    "notification_type": "General"
}
```

## Reporting API

### Member Reports
```http
GET /api/reports/members
```

#### Query Parameters
- `start_date` (date) - Report start date
- `end_date` (date) - Report end date
- `format` (string) - Response format (json, csv, pdf)

#### Response
```json
{
    "status": "success",
    "data": {
        "summary": {
            "total_members": 150,
            "active_members": 140,
            "inactive_members": 10,
            "new_members_this_month": 5
        },
        "by_membership_type": [
            {
                "type": "Regular",
                "count": 120
            },
            {
                "type": "Premium",
                "count": 30
            }
        ],
        "age_distribution": [
            {
                "age_range": "18-30",
                "count": 45
            },
            {
                "age_range": "31-45",
                "count": 60
            }
        ]
    }
}
```

### Financial Reports
```http
GET /api/reports/financial
```

### Loan Reports
```http
GET /api/reports/loans
```

## Admin API

### List Admins
```http
GET /api/admins
```

### Create Admin
```http
POST /api/admins
Content-Type: application/json

{
    "username": "newadmin",
    "password": "securepassword",
    "first_name": "Jane",
    "last_name": "Smith",
    "email": "jane.smith@example.com",
    "role": "Admin"
}
```

## Response Formats

### Success Response
```json
{
    "status": "success",
    "data": {
        // Response data
    },
    "message": "Operation completed successfully",
    "timestamp": "2024-01-15T10:30:00Z"
}
```

### Error Response
```json
{
    "status": "error",
    "error_code": "ERROR_CODE",
    "message": "Error description",
    "details": {
        // Additional error details
    },
    "timestamp": "2024-01-15T10:30:00Z"
}
```

### Pagination
```json
{
    "pagination": {
        "current_page": 1,
        "total_pages": 10,
        "total_items": 100,
        "items_per_page": 10,
        "has_next": true,
        "has_previous": false
    }
}
```

## Code Examples

### JavaScript (Fetch API)
```javascript
// Login
const login = async (username, password) => {
    const response = await fetch('/api/auth/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            username,
            password,
            user_type: 'admin'
        })
    });
    
    const data = await response.json();
    return data;
};

// Get members
const getMembers = async (page = 1, limit = 10) => {
    const response = await fetch(`/api/members?page=${page}&limit=${limit}`, {
        method: 'GET',
        credentials: 'include' // Include session cookies
    });
    
    const data = await response.json();
    return data;
};

// Create member
const createMember = async (memberData) => {
    const response = await fetch('/api/members', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify(memberData)
    });
    
    const data = await response.json();
    return data;
};
```

### PHP (cURL)
```php
// Login
function login($username, $password) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/CSIMS/api/auth/login');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'username' => $username,
        'password' => $password,
        'user_type' => 'admin'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Get members
function getMembers($page = 1, $limit = 10) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/CSIMS/api/members?page={$page}&limit={$limit}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
```

### Python (Requests)
```python
import requests
import json

class CSIMSClient:
    def __init__(self, base_url):
        self.base_url = base_url
        self.session = requests.Session()
    
    def login(self, username, password, user_type='admin'):
        url = f"{self.base_url}/auth/login"
        data = {
            'username': username,
            'password': password,
            'user_type': user_type
        }
        response = self.session.post(url, json=data)
        return response.json()
    
    def get_members(self, page=1, limit=10):
        url = f"{self.base_url}/members"
        params = {'page': page, 'limit': limit}
        response = self.session.get(url, params=params)
        return response.json()
    
    def create_member(self, member_data):
        url = f"{self.base_url}/members"
        response = self.session.post(url, json=member_data)
        return response.json()

# Usage
client = CSIMSClient('http://localhost/CSIMS/api')
login_result = client.login('admin', 'password')
members = client.get_members(page=1, limit=20)
```

---

*This API documentation is maintained by the development team and updated with each system release.*