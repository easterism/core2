# Core2 Framework - Code Review Report

**Date**: 2026-02-01  
**Repository**: easterism/core2  
**Reviewer**: Automated Code Review  
**Framework Type**: PHP Business Application Framework  

---

## Executive Summary

This comprehensive code review of the Core2 framework identifies critical security vulnerabilities, code quality issues, and technical debt that should be addressed to improve the overall health, security, and maintainability of the codebase.

### Key Metrics
- **Total PHP Files**: 123
- **Lines of Code**: Substantial (1600+ lines in Init.php alone)
- **TODO/FIXME Items**: 58 instances
- **PHP Version**: 8.2+ (composer.json) vs 7.4+ (README.md) - **Inconsistent**
- **Test Coverage**: Limited (6 test files found)

### Severity Levels
- 🔴 **Critical**: Immediate attention required (Security vulnerabilities)
- 🟠 **High**: Should be addressed soon (Code quality, maintainability)
- 🟡 **Medium**: Plan to address (Technical debt, improvements)
- 🟢 **Low**: Nice to have (Documentation, minor improvements)

---

## 🔴 Critical Issues

### 1. Security Vulnerabilities

#### 1.1 Use of `eval()` - CRITICAL SECURITY RISK
**Location**: [`inc/classes/class.tree.php`](inc/classes/class.tree.php:213-235), [`inc/classes/listTable2.php`](inc/classes/listTable2.php:376-406), [`inc/classes/class.list.php`](inc/classes/class.list.php:682-711)

**Issue**: Direct use of `eval()` with potentially user-controlled data.

```php
// class.tree.php:213
eval($this->currentTrunc . "['DATA'] = \$data;");

// listTable2.php:376
eval("\$sql_value = " . $value['processing'] . "(\$row);");

// listTable2.php:405
eval("if ($tres) \$a = 1;");
```

**Risk**: Remote Code Execution (RCE)
**Recommendation**: Replace `eval()` with safer alternatives like callbacks, anonymous functions, or structured data processing.

#### 1.2 Weak Password Hashing
**Location**: Multiple files using MD5

**Issue**: MD5 is cryptographically broken and should not be used for password hashing.

```php
// mod/admin/ModAjax.php:600
$dataForSave['u_pass'] = Tool::pass_salt(md5($data['control']['u_pass']));

// inc/MobileController.php:666
$dataForSave['u_pass'] = md5($data['control']['u_pass']);
```

**Recommendation**: Use `password_hash()` with `PASSWORD_ARGON2ID` or `PASSWORD_BCRYPT`.

```php
// Recommended approach
$dataForSave['u_pass'] = password_hash($data['control']['u_pass'], PASSWORD_ARGON2ID);
// Verify with: password_verify($input_password, $stored_hash)
```

#### 1.3 Unsanitized Superglobal Access
**Location**: Throughout the codebase (220+ instances)

**Issue**: Direct access to `$_GET`, `$_POST`, `$_REQUEST`, `$_SERVER` without proper validation or sanitization.

**Examples**:
```php
// inc/CoreController.php:277
if (isset($_GET['edit'])) {
    $module = $this->dataModules->getRowById((int)$_GET['edit']);
}

// mod/admin/classes/monitoring/Monitoring.php:26
$search = $_GET['search'];
```

**Recommendation**: 
- Use input validation library or create wrapper functions
- Apply proper type casting and validation before use
- Use filter_input() functions

```php
// Better approach
$edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
if ($edit_id !== false && $edit_id !== null) {
    $module = $this->dataModules->getRowById($edit_id);
}
```

#### 1.4 Command Injection Risk
**Location**: [`inc/classes/Tool.php`](inc/classes/Tool.php:361), [`mod/admin/ModAjax.php`](mod/admin/ModAjax.php:940), [`mod/admin/classes/modules/InstallModule.php`](mod/admin/classes/modules/InstallModule.php:2573)

**Issue**: Use of `exec()`, `shell_exec()` with potentially unsafe input.

```php
// Tool.php:361
exec($cmd . " > /dev/null &");

// ModAjax.php:940
$tmp = exec("{$php_path} -l {$path}");

// InstallModule.php:2573
exec("composer update 2>&1", $output, $return_var);
```

**Recommendation**: 
- Use `escapeshellarg()` and `escapeshellcmd()` for all user input
- Avoid shell execution when possible
- Use PHP native functions instead

---

## 🟠 High Priority Issues

### 2. Code Quality Issues

#### 2.1 Inconsistent PHP Version Requirements
**Location**: [`README.md`](README.md:8) vs [`composer.json`](composer.json:24)

- README.md states: "PHP 7.4 or greater"
- composer.json requires: "php": ">=8.2"

**Impact**: Misleading documentation, potential deployment issues.
**Recommendation**: Update README.md to reflect actual requirement of PHP 8.2+.

#### 2.2 Missing Composer Dependencies
**Issue**: `composer install` command fails (composer not found in test environment).

**Recommendation**: 
- Ensure proper deployment documentation
- Consider containerization (Docker) for consistent environments
- Add `.gitattributes` to include vendor directory handling

#### 2.3 Excessive TODO/FIXME Comments (58 instances)
**Impact**: Indicates incomplete functionality and technical debt.

**Top Priority TODOs**:
1. `inc/classes/Init.php:20` - "FIXME Нужно убрать exit()"
2. `inc/classes/Db.php:229` - "FIXME грязный хак для того чтобы сработал сеттер базы данных"
3. `mod/admin/classes/modules/InstallModule.php:552` - "FIXME Не работает lastInsertId()"
4. `inc/classes/Acl.php:335-339` - "TODO SHOULD BE FIX" (twice)

**Recommendation**: Create issues for each TODO/FIXME and prioritize resolution.

### 3. Architecture & Design Issues

#### 3.1 God Classes
**Location**: [`inc/classes/Init.php`](inc/classes/Init.php) (1624 lines)

**Issue**: The Init class handles too many responsibilities:
- Configuration loading
- Database connection
- Session management
- Routing
- Authentication
- Module loading
- Request handling

**Recommendation**: Apply Single Responsibility Principle (SRP) and split into:
- `ConfigLoader`
- `DatabaseConnector`
- `SessionManager`
- `Router`
- `AuthenticationManager`
- `ModuleLoader`

#### 3.2 Mixed Concerns in Controllers
**Issue**: Controllers contain business logic, data access, and presentation logic.

**Recommendation**: Implement proper MVC/service layer architecture:
```
Controller -> Service -> Repository -> Model
```

#### 3.3 Global State and Registry Pattern
**Location**: [`inc/classes/Registry.php`](inc/classes/Registry.php)

**Issue**: Heavy use of Registry pattern creates hidden dependencies and makes testing difficult.

**Recommendation**: Use Dependency Injection (DI) container instead.

---

## 🟡 Medium Priority Issues

### 4. Testing Issues

#### 4.1 Limited Test Coverage
**Found Tests**:
- `tests/inc/classes/AclTest.php`
- `tests/inc/classes/AlertTest.php`
- `tests/inc/classes/LogTest.php`
- `tests/inc/classes/ToolsTest.php`

**Issue**: Only 6 test files for 123 PHP files.

**Recommendation**: 
- Aim for at least 70% code coverage
- Implement CI/CD with automated testing
- Add integration and end-to-end tests

#### 4.2 PHPStan Configuration
**Location**: [`phpstan.neon`](phpstan.neon:2)

**Current Level**: 6 (out of 9)

**Recommendation**: 
- Gradually increase to level 8 or 9
- Fix all reported issues at current level first
- Add PHPStan to CI/CD pipeline

### 5. Documentation Issues

#### 5.1 Incomplete Installation Instructions
**Location**: [`README.md`](README.md:14-46)

**Missing**:
- Environment setup details
- Development workflow
- Testing instructions
- Contribution guidelines
- API documentation

**Recommendation**: Add comprehensive documentation including:
- CONTRIBUTING.md
- CHANGELOG.md
- API documentation (consider using phpDocumentor)
- Development environment setup guide

#### 5.2 Missing Type Declarations
**Issue**: Many methods lack return type declarations and parameter types.

**Recommendation**: Add strict typing:
```php
declare(strict_types=1);

public function getUserById(int $id): ?User {
    // implementation
}
```

---

## 🟢 Low Priority Issues

### 6. Code Style & Consistency

#### 6.1 Mixed Coding Standards
**Issue**: Inconsistent formatting, naming conventions, and code structure.

**Recommendation**: 
- Adopt PSR-12 coding standard
- Use PHP CS Fixer or PHP_CodeSniffer
- Add `.editorconfig` file

#### 6.2 Commented Code
**Issue**: Multiple instances of commented-out code throughout the codebase.

**Recommendation**: Remove commented code (it's in version control) or move to documentation if needed.

#### 6.3 Magic Numbers
**Issue**: Hard-coded values without explanation.

**Example**:
```php
// inc/classes/Error.php:36
if ($code == 13) { //ошибки для js объекта с наличием error
```

**Recommendation**: Use named constants:
```php
const ERROR_CODE_JS_OBJECT = 13;
```

---

## 📊 Positive Aspects

### Strengths of the Codebase

1. ✅ **Modern Dependencies**: Uses Laminas components (modern Zend Framework successor)
2. ✅ **Modular Architecture**: Clear module structure in `mod/` directory
3. ✅ **Database Abstraction**: Uses PDO and Zend_Db adapter pattern
4. ✅ **Internationalization**: Has I18n support built-in
5. ✅ **Caching Support**: Multiple cache adapters (Redis, Memcached, Filesystem)
6. ✅ **Worker System**: Background job processing with Gearman support
7. ✅ **ACL Implementation**: Role-based access control system
8. ✅ **MIT License**: Open source friendly license

---

## 📋 Recommendations Summary

### Immediate Actions (Critical)

1. **Replace all `eval()` calls** with safe alternatives
2. **Migrate from MD5 to bcrypt/Argon2** for password hashing
3. **Implement input validation layer** for all superglobals
4. **Sanitize all shell command inputs** or eliminate shell calls
5. **Fix PHP version documentation** inconsistency

### Short-term Actions (High Priority)

1. **Address TODO/FIXME items** - Create tracking issues
2. **Refactor Init class** - Break into smaller, focused classes
3. **Improve test coverage** - Aim for 70%+
4. **Add strict type declarations** throughout
5. **Implement proper dependency injection**

### Medium-term Actions

1. **Increase PHPStan level** to 8 or 9
2. **Add comprehensive documentation**
3. **Implement CI/CD pipeline** with:
   - Automated testing
   - Static analysis
   - Security scanning
4. **Adopt PSR-12 coding standards**
5. **Remove dead/commented code**

### Long-term Actions

1. **Consider migration to modern framework** (Symfony, Laravel) or
2. **Major refactoring** to follow SOLID principles
3. **Microservices architecture** for scalability
4. **API-first design** with proper versioning
5. **Comprehensive security audit** by security professionals

---

## 🔒 Security Checklist

- [ ] Remove all `eval()` usage
- [ ] Replace MD5 password hashing with bcrypt/Argon2
- [ ] Implement input validation for all user inputs
- [ ] Add CSRF protection for all forms
- [ ] Sanitize all database queries (use prepared statements)
- [ ] Validate and escape all shell command inputs
- [ ] Implement rate limiting for authentication
- [ ] Add security headers (CSP, HSTS, etc.)
- [ ] Regular dependency updates for security patches
- [ ] Implement proper session management
- [ ] Add SQL injection prevention measures
- [ ] XSS prevention in output
- [ ] Implement proper error handling (don't expose internals)

---

## 📈 Code Quality Metrics

| Metric | Current | Target | Priority |
|--------|---------|--------|----------|
| Test Coverage | ~5% | 70%+ | High |
| PHPStan Level | 6 | 8 | Medium |
| TODO/FIXME Count | 58 | 0 | High |
| eval() Usage | 6+ | 0 | Critical |
| MD5 Password Hashing | Yes | No | Critical |
| Type Declarations | Partial | Complete | Medium |
| Documentation | Basic | Comprehensive | Medium |

---

## 🎯 Conclusion

The Core2 framework shows a solid foundation with modern dependencies and a modular architecture. However, it suffers from several critical security vulnerabilities and technical debt that must be addressed urgently.

**Priority Focus Areas**:
1. **Security**: Address critical vulnerabilities immediately
2. **Code Quality**: Reduce technical debt and improve maintainability
3. **Testing**: Increase coverage for reliability
4. **Documentation**: Improve for better adoption and maintenance

**Overall Rating**: ⭐⭐⭐ (3/5)
- **Security**: 🔴 Critical issues present
- **Code Quality**: 🟡 Needs improvement
- **Architecture**: 🟠 Good foundation, needs refactoring
- **Documentation**: 🟡 Basic, needs expansion
- **Testing**: 🔴 Insufficient coverage

With focused effort on the recommendations above, this framework can become a robust, secure, and maintainable solution for business applications.

---

## 📞 Next Steps

1. Review and prioritize findings with the development team
2. Create GitHub issues for each critical and high-priority item
3. Establish a remediation roadmap with timelines
4. Set up automated security scanning in CI/CD
5. Schedule regular code reviews and security audits

---

**Report Generated**: 2026-02-01  
**Framework Version**: 2.9.0  
**Review Type**: Comprehensive Code Review
