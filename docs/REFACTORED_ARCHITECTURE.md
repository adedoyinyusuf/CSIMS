# CSIMS Refactored Architecture

This document outlines the comprehensive refactoring of the Credit and Savings Information Management System (CSIMS) using modern PHP practices, SOLID principles, and a clean architecture approach.

## Overview

The refactored system introduces:
- **Dependency Injection** for loose coupling
- **Service Layer** for business logic
- **Repository Pattern** for data access
- **Query Builder** for safe SQL generation
- **Model Layer** with validation
- **Exception Handling** for error management
- **Security Service** for input sanitization and CSRF protection

## Directory Structure

```
src/
├── Models/              # Domain models (Member, Loan, etc.)
├── Repositories/        # Data access layer
├── Services/            # Business logic layer
├── Controllers/         # Request handling (refactored examples)
├── Database/            # Query builder and database utilities
├── Container/           # Dependency injection container
├── DTOs/                # Data Transfer Objects
├── Interfaces/          # Contracts and interfaces
├── Exceptions/          # Custom exception classes
└── bootstrap.php        # Application initialization
```

## Key Components

### 1. Models (`src/Models/`)

Models represent domain entities with:
- Properties and getters/setters
- Data validation using SecurityService
- Array serialization/deserialization
- Business logic methods

**Example: Loan Model**
- Calculates monthly payments using financial formulas
- Validates loan data (amount, interest rate, term)
- Manages loan lifecycle states
- Integrates with member information

### 2. Repositories (`src/Repositories/`)

Repositories handle data persistence with:
- CRUD operations
- Query building using QueryBuilder
- Prepared statements for security
- Pagination and filtering
- Custom query methods

**Features:**
- `LoanRepository`: Complete loan management with member joins
- `MemberRepository`: Member data operations with search capabilities
- Unified interface through `RepositoryInterface`

### 3. Services (`src/Services/`)

Services contain business logic:
- Input validation and sanitization
- Business rule enforcement
- Complex operations coordination
- Exception handling

**Example: LoanService**
- Loan creation with validation
- Payment processing
- Business rule enforcement (loan limits, member restrictions)
- Payment schedule calculations

### 4. Security Service (`src/Services/SecurityService.php`)

Centralized security handling:
- Input sanitization (XSS prevention)
- CSRF token generation/validation
- Rate limiting
- Security headers
- SQL injection prevention through parameterized queries

### 5. Query Builder (`src/Database/QueryBuilder.php`)

Fluent SQL query construction:
- SELECT, INSERT, UPDATE, DELETE operations
- JOIN support (INNER, LEFT, RIGHT)
- WHERE clauses with operators
- Pagination (LIMIT/OFFSET)
- Parameter binding for security

**Example Usage:**
```php
$query = QueryBuilder::table('loans')
    ->select(['l.*', 'm.first_name', 'm.last_name'])
    ->leftJoin('members m', 'l.member_id', '=', 'm.member_id')
    ->where('l.status', 'Active')
    ->orderBy('l.created_at', 'DESC')
    ->limit(10);

[$sql, $params] = $query->build();
```

### 6. Dependency Injection Container (`src/Container/Container.php`)

Manages object dependencies:
- Service binding and resolution
- Automatic dependency injection
- Singleton pattern for shared instances
- Constructor parameter resolution

### 7. Exception Handling (`src/Exceptions/`)

Structured error management:
- `CSIMSException`: Base exception
- `ValidationException`: Input validation errors
- `DatabaseException`: Database operation errors
- `SecurityException`: Security-related errors
- `ContainerException`: DI container errors

## Usage Examples

### Bootstrap Application

```php
<?php
require_once 'src/bootstrap.php';

// Initialize the application
$container = CSIMS\bootstrap();

// Get services
$loanService = $container->resolve(CSIMS\Services\LoanService::class);
$memberService = $container->resolve(CSIMS\Services\MemberService::class);
```

### Create a Loan

```php
try {
    $loanData = [
        'member_id' => 1,
        'amount' => 5000.00,
        'interest_rate' => 12.5,
        'term_months' => 24,
        'purpose' => 'Business expansion'
    ];
    
    $loan = $loanService->createLoan($loanData);
    echo "Loan created with ID: " . $loan->getId();
    
} catch (\CSIMS\Exceptions\ValidationException $e) {
    echo "Validation error: " . $e->getMessage();
} catch (\CSIMS\Exceptions\DatabaseException $e) {
    echo "Database error occurred";
}
```

### Query with Filters

```php
$filters = [
    'status' => 'Active',
    'amount' => ['>', 1000]
];

$result = $loanService->getLoans($filters, 1, 10);

foreach ($result['data'] as $loan) {
    echo "Loan ID: " . $loan->getId();
    echo "Amount: $" . $loan->getAmount();
    echo "Status: " . $loan->getStatus();
}
```

### Controller Example (Refactored)

```php
<?php
// Modern controller using dependency injection
class LoanController 
{
    private LoanService $loanService;
    private SecurityService $securityService;
    
    public function __construct(LoanService $loanService, SecurityService $securityService)
    {
        $this->loanService = $loanService;
        $this->securityService = $securityService;
    }
    
    public function create(array $requestData): array
    {
        try {
            // Validate CSRF token
            if (!$this->securityService->validateCsrfToken($requestData['csrf_token'] ?? '')) {
                throw new ValidationException('Invalid CSRF token');
            }
            
            $loan = $this->loanService->createLoan($requestData);
            
            return [
                'success' => true,
                'data' => $loan->toArray(),
                'id' => $loan->getId()
            ];
            
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [$e->getMessage()]
            ];
        }
    }
}
```

## Migration Strategy

### Phase 1: Foundation (Completed)
- ✅ Directory structure creation
- ✅ Base interfaces and contracts
- ✅ Dependency injection container
- ✅ Security service implementation
- ✅ Exception handling system

### Phase 2: Core Models and Repositories (Completed)
- ✅ Member and Loan models
- ✅ Repository implementations
- ✅ Query builder system
- ✅ Validation framework

### Phase 3: Business Logic (Completed)
- ✅ Service layer implementation
- ✅ Business rule enforcement
- ✅ Payment calculations
- ✅ Workflow management

### Phase 4: Controller Refactoring (Example Provided)
- ✅ Modern controller example
- 🔄 Legacy controller migration
- 🔄 API endpoint standardization
- 🔄 Error response formatting

### Phase 5: Integration (Next Steps)
- 🔄 Database migration scripts
- 🔄 Frontend integration
- 🔄 Testing framework
- 🔄 Documentation updates

## Benefits of the Refactored Architecture

### Security Improvements
- **Input Sanitization**: All inputs sanitized through SecurityService
- **SQL Injection Prevention**: Parameterized queries only
- **CSRF Protection**: Token validation on state-changing operations
- **XSS Prevention**: HTML encoding of outputs

### Maintainability
- **Separation of Concerns**: Clear boundaries between layers
- **SOLID Principles**: Single responsibility, dependency inversion
- **DRY Code**: Reusable components and services
- **Testability**: Dependency injection enables easy testing

### Performance
- **Optimized Queries**: Query builder generates efficient SQL
- **Connection Management**: Single database connection per request
- **Lazy Loading**: Services instantiated on-demand
- **Caching Ready**: Architecture supports caching layers

### Scalability
- **Modular Design**: Easy to add new features
- **Service Decoupling**: Independent scaling of components
- **Database Abstraction**: Easy to change database systems
- **API Ready**: Clean separation enables API development

## Security Features

### Input Validation
- Type checking and sanitization
- Business rule validation
- Required field checking
- Data format validation

### Authentication & Authorization
- CSRF token protection
- Rate limiting capability
- Session management integration
- Role-based access (extensible)

### Data Protection
- Prepared statements for all queries
- HTML encoding for output
- Error message sanitization
- Secure password handling (when implemented)

## Error Handling

### Exception Hierarchy
```
CSIMSException (base)
├── ValidationException (input validation errors)
├── DatabaseException (database operation errors)
├── SecurityException (security-related errors)
└── ContainerException (dependency injection errors)
```

### Error Response Format
```json
{
    "success": false,
    "message": "User-friendly error message",
    "errors": ["Detailed error messages"],
    "debug": {  // Only in debug mode
        "file": "path/to/file.php",
        "line": 123,
        "trace": "Stack trace..."
    }
}
```

## Configuration

### Environment Variables
```bash
DB_HOST=localhost
DB_USERNAME=root
DB_PASSWORD=your_password
DB_DATABASE=csims
DB_PORT=3306
DB_CHARSET=utf8mb4
APP_DEBUG=false
```

### Database Requirements
- MySQL 5.7+ or MariaDB 10.2+
- UTF8MB4 charset support
- InnoDB storage engine recommended

## Next Steps

1. **Complete Model Implementation**: Implement remaining models (Contribution, Transaction, etc.)
2. **Extend Service Layer**: Add services for all business domains
3. **API Development**: Create comprehensive REST API endpoints
4. **Frontend Integration**: Update views to use new architecture
5. **Testing**: Implement unit and integration tests
6. **Documentation**: Create API documentation and user guides
7. **Performance Optimization**: Add caching and query optimization
8. **Deployment**: Create production deployment scripts

## Best Practices Applied

- **PSR-4 Autoloading**: Namespace-based class loading
- **PSR-12 Coding Standards**: Consistent code formatting
- **SOLID Principles**: Maintainable object-oriented design
- **Repository Pattern**: Clean data access abstraction
- **Service Layer**: Business logic separation
- **Dependency Injection**: Loose coupling and testability
- **Exception Handling**: Proper error management
- **Security First**: Input validation and output encoding

This refactored architecture provides a solid foundation for the CSIMS system, addressing security vulnerabilities, improving maintainability, and enabling future enhancements while following modern PHP development practices.
