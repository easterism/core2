# DbTest - Unit Tests for Core2\Db Class

## Overview

This test suite provides comprehensive unit tests for the `Core2\Db` class, covering:
- Constructor initialization
- Magic methods (`__get`)
- Database connection methods
- Settings management
- Enum/dictionary operations
- Session management
- Activity logging
- Translation methods

## Test Coverage

### Core Functionality
- ✅ Instance creation with and without config
- ✅ Configuration initialization
- ✅ Schema name handling
- ✅ Registry integration

### Database Connection Methods
- ✅ `establishConnection()` - Establishes database connections
- ✅ `getConnection()` - Creates database adapter
- ✅ `newConnector()` - Creates custom connections with various parameters
- ✅ Multiple adapter support (MySQL, PostgreSQL)

### Magic Methods
- ✅ `__get('core_config')` - Core configuration access
- ✅ `__get('cache')` - Cache adapter access
- ✅ `__get('db')` - Database connection access
- ✅ `__get('translate')` - Translation service access
- ✅ `__get('log')` - Logging service access

### Settings Management
- ✅ `getSetting()` - Get system settings
- ✅ `getCustomSetting()` - Get custom settings
- ✅ `getPersonalSetting()` - Get personal settings

### Enum/Dictionary Operations
- ✅ `getEnumList()` - Get enum values list
- ✅ `getEnumDropdown()` - Get enum as dropdown options
- ✅ `getEnumValueById()` - Get single enum value
- ✅ `getEnumById()` - Get enum with metadata
- ✅ Custom field parsing

### Session & Activity
- ✅ `closeSession()` - Close user session
- ✅ `logActivity()` - Log user activity

### Module Management
- ✅ `getModuleName()` - Get module name by ID

### Translation
- ✅ `_()` - Translation method with module support

## Running the Tests

### Prerequisites

1. **PHP 8.2+** installed
2. **Composer dependencies** installed:
   ```bash
   cd /path/to/core2
   composer install
   ```

3. **PHPUnit** configured (included in composer dev dependencies)

### Basic Test Execution

```bash
# Run all tests
vendor/bin/phpunit tests/inc/classes/DbTest.php

# Run with verbose output
vendor/bin/phpunit --verbose tests/inc/classes/DbTest.php

# Run specific test method
vendor/bin/phpunit --filter testCanCreateDbInstance tests/inc/classes/DbTest.php

# Run with coverage report (requires Xdebug)
vendor/bin/phpunit --coverage-html coverage tests/inc/classes/DbTest.php
```

### Using PHPUnit Configuration

If you have a `phpunit.xml` configuration:

```bash
# Run all Db tests
vendor/bin/phpunit --group Database

# Run with testdox output (human-readable)
vendor/bin/phpunit --testdox tests/inc/classes/DbTest.php
```

## Test Structure

### Setup and Teardown

Each test uses:
- `setUp()`: Creates mock configuration and Db instance
- `tearDown()`: Cleans up resources and Registry

### Mock Configuration

Tests use a mock configuration with:
- **System settings**: timezone, name
- **Cache settings**: Filesystem adapter
- **Database settings**: MySQL connection parameters

```php
$mockConfig = new LaminasConfig([
    'system' => ['name' => 'TestSystem', 'timezone' => 'UTC'],
    'cache' => ['adapter' => 'Filesystem', ...],
    'database' => ['adapter' => 'Pdo_Mysql', ...]
]);
```

## Data Providers

The test suite includes data providers for parameterized testing:

### `moduleNameProvider()`
Tests various module ID formats:
- Simple modules: `'admin'`
- Modules with underscores: `'test_module'`
- Submodules: `'admin_users'`
- Invalid formats: `''`

### `connectorParametersProvider()`
Tests various connection parameters:
- Standard MySQL
- MySQL with custom port
- PostgreSQL
- Different charsets (utf8, utf8mb4)

## Expected Behaviors

### Without Database Connection

Many tests expect exceptions when no database is available:
- Tests catch exceptions and verify they are `\Exception` instances
- This is expected behavior in isolated unit testing

### With Database Connection

For integration testing with actual database:
1. Configure test database in `tests/bootstrap.php`
2. Set environment variables:
   ```bash
   export DB_HOST=localhost
   export DB_NAME=test_core2
   export DB_USER=test_user
   export DB_PASSWD=test_pass
   ```
3. Run migrations/setup test database schema
4. Tests will connect and verify actual functionality

## Test Categories

### Unit Tests (No Database Required)
- Instance creation
- Configuration handling
- Parameter validation
- Array/string return type checks

### Integration Tests (Database Required)
- Actual database connections
- Query execution
- Data retrieval
- Transaction handling

## Mocking Strategies

The test suite uses several mocking approaches:

1. **Configuration Mocking**: Using `LaminasConfig` with test data
2. **Registry Mocking**: Setting mock objects in Registry
3. **Reflection**: Accessing private/protected methods for testing
4. **Exception Handling**: Catching expected exceptions

## Known Limitations

1. **Database Dependencies**: Some methods require actual database connection
2. **Registry State**: Global Registry state may affect test isolation
3. **External Dependencies**: Some tests require module infrastructure

## Extending the Tests

To add new tests:

```php
/**
 * Test description
 */
public function testNewFeature(): void {
    // Arrange
    $expected = 'expected_value';
    
    // Act
    $result = $this->db->newMethod();
    
    // Assert
    $this->assertEquals($expected, $result);
}
```

## Code Coverage Goals

Current coverage goals:
- **Target**: 80%+ code coverage
- **Critical paths**: 100% coverage
- **Edge cases**: All exception paths covered

Check coverage:
```bash
vendor/bin/phpunit --coverage-text tests/inc/classes/DbTest.php
```

## Continuous Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo, pdo_mysql
      - run: composer install
      - run: vendor/bin/phpunit tests/inc/classes/DbTest.php
```

## Troubleshooting

### Common Issues

1. **"Class not found" errors**
   - Ensure composer autoload is working: `composer dump-autoload`
   - Check bootstrap.php is loaded

2. **"Registry not found" errors**
   - Registry requires proper initialization in bootstrap
   - Check DOC_ROOT constant is defined

3. **"Database connection failed" errors**
   - Expected for unit tests without database
   - For integration tests, verify database credentials

4. **Memory exhaustion**
   - Increase PHP memory: `php -d memory_limit=512M vendor/bin/phpunit`

## Best Practices

1. **Isolation**: Each test should be independent
2. **Cleanup**: Always clean up in tearDown()
3. **Assertions**: Use specific assertion methods
4. **Documentation**: Document expected behavior
5. **Data Providers**: Use for parameterized tests

## Contributing

When adding new tests:
1. Follow PSR-12 coding standards
2. Add PHPDoc comments
3. Use descriptive test names
4. Update this README
5. Ensure tests pass before committing

## References

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Laminas Documentation](https://docs.laminas.dev/)
- [Core2 Framework Documentation](../../../README.md)

---

**Last Updated**: 2026-02-01  
**Test Suite Version**: 1.0  
**Minimum PHP Version**: 8.2
