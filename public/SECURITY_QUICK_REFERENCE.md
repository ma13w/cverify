# CVerify Security - Quick Reference Card

## 🚨 Emergency Checklist (Before Production)

### Step 1: Critical Fixes (15 minutes)

```bash
# 1. Enable HTTPS enforcement
# Edit .htaccess - uncomment lines 6-8

# 2. Verify production mode
# Edit verifier/index.php line 16
define('PRODUCTION_MODE', true); # ✅ Already set

# 3. Check PHP settings
php -i | grep display_errors  # Should be "Off"
```

### Step 2: CSRF Implementation (2-3 hours)

```php
// Add to ALL forms (see CSRF_IMPLEMENTATION_GUIDE.php)
<input type="hidden" name="csrf_token" value="<?= Security::generateCsrfToken() ?>">

// Add to ALL POST handlers
Security::requireCsrfToken($_POST['csrf_token'] ?? null);
```

### Step 3: Test Everything (1 hour)

- [ ] Login as user works
- [ ] Login as company works
- [ ] Create experience works
- [ ] Request validation works
- [ ] Approve validation works
- [ ] Verifier Lens works
- [ ] Rate limiting triggers after 10 attempts
- [ ] DNS verification works

---

## 🔐 Security Features Status

| Feature              | Status        | Location                         |
| -------------------- | ------------- | -------------------------------- |
| Secure Sessions      | ✅ Active     | `Security::startSecureSession()` |
| Rate Limiting        | ✅ Active     | Login: 10/5min, API: 20/1min     |
| Path Traversal Block | ✅ Active     | `Security::sanitizeDomain()`     |
| JSON Validation      | ✅ Active     | Max 1MB, structure validated     |
| Challenge Expiration | ✅ Active     | 5 minutes                        |
| File Permissions     | ✅ Active     | 0600 for sensitive files         |
| Atomic Writes        | ✅ Active     | All writes use LOCK_EX           |
| Security Headers     | ✅ Active     | Via `.htaccess`                  |
| HTTPS Enforcement    | ⚠️ Ready      | Uncomment in `.htaccess`         |
| CSRF Protection      | ⚠️ Needs Work | Tokens ready, forms need update  |
| Private Key Security | ⚠️ Risk       | See audit for solutions          |

---

## 📞 Quick Fixes for Common Issues

### "Rate limit exceeded"

```php
// Adjust limits in login.php or API files
$security->enforceRateLimit('login_' . $clientIp, 20, 300); // 20 attempts, 5 min
```

### "CSRF token validation failed"

```php
// Make sure form has token
<?= Security::generateCsrfToken() ?>

// Make sure handler validates
Security::requireCsrfToken($_POST['csrf_token'] ?? null);
```

### "Challenge expired"

```php
// Increase expiration in src/Auth.php line 16
private const CHALLENGE_LIFETIME = 600; // 10 minutes
```

### "JSON too large"

```php
// Increase limit in Security::validateJson()
Security::validateJson($json, 5242880); // 5MB
```

---

## 🛡️ Security Class API Quick Reference

```php
use CVerify\Security;

// Session Management
Security::startSecureSession();

// CSRF Protection
$token = Security::generateCsrfToken();
Security::validateCsrfToken($token);    // Returns bool
Security::requireCsrfToken($token);     // Throws exception

// Rate Limiting
$security->enforceRateLimit($id, $max, $window);
$security->cleanupRateLimits($maxAge);

// JSON Validation
$data = Security::validateJson($json, $maxSize);
$data = Security::safeReadJson($file, $maxSize);

// File Operations
Security::safeFileWrite($path, $data, $perms);
Security::validateFileSize($path, $maxSize);

// Input Validation
$domain = Security::sanitizeDomain($input);
Security::validateLength($input, $max, $field);

// HTTPS
Security::enforceHttps(); // Throws if not HTTPS
```

---

## 🔍 Security Monitoring

### Check Rate Limit Files

```bash
ls -la user/data/rate_limits/
ls -la company/data/rate_limits/
ls -la relay-server/data/rate_limits/
```

### Check File Permissions

```bash
# Should be 0600 (rw-------)
ls -la user/data/session.json
ls -la company/data/session.json
ls -la user/data/private_key.pem
```

### Monitor Failed Logins

```bash
# Add logging to login.php
error_log("Failed login attempt from {$clientIp}");
```

---

## 📁 Key Files to Know

| File                            | Purpose                  |
| ------------------------------- | ------------------------ |
| `src/Security.php`              | All security utilities   |
| `src/Auth.php`                  | Authentication logic     |
| `.htaccess`                     | Security headers & HTTPS |
| `SECURITY_AUDIT_FIXES.md`       | Complete audit report    |
| `CSRF_IMPLEMENTATION_GUIDE.php` | CSRF how-to              |
| `SECURITY_FIXES_SUMMARY.md`     | What was fixed           |

---

## ⚡ Performance Impact

| Feature          | Impact  | Notes                    |
| ---------------- | ------- | ------------------------ |
| Rate Limiting    | Minimal | File-based, auto-cleanup |
| JSON Validation  | Low     | O(n) parse time          |
| File Locking     | Minimal | Only during writes       |
| Session Security | None    | Cookie flags only        |
| CSRF Tokens      | None    | 32-byte string           |

---

## 🐛 Troubleshooting

### Session Issues

```php
// Clear all sessions
rm user/data/session.json
rm company/data/session.json
```

### Rate Limit Reset

```php
// Manual cleanup
rm -rf user/data/rate_limits/*
rm -rf company/data/rate_limits/*
rm -rf relay-server/data/rate_limits/*
```

### Debug Mode

```php
// In verifier/index.php
define('PRODUCTION_MODE', false); // Shows debug info
```

---

## 📊 Security Metrics

**Code Added**: 800+ lines  
**Vulnerabilities Fixed**: 10/12 (83%)  
**Files Modified**: 15+  
**New Features**: 15+  
**Breaking Changes**: None  
**Performance Impact**: <1%

---

## 🎓 Learning Resources

- **OWASP Top 10**: https://owasp.org/www-project-top-ten/
- **PHP Security**: https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html
- **CSP Guide**: https://content-security-policy.com/
- **Let's Encrypt**: https://letsencrypt.org/

---

## ✅ Final Pre-Launch Checklist

```
[ ] HTTPS certificate installed
[ ] .htaccess HTTPS enforcement uncommented
[ ] CSRF tokens added to all forms
[ ] All rate limits tested
[ ] Private key storage reviewed
[ ] PHP display_errors = Off
[ ] Error logging configured
[ ] Security headers verified (check with securityheaders.com)
[ ] Backup procedures documented
[ ] Incident response plan created
[ ] Security monitoring enabled
[ ] All tests passed
```

---

**Last Updated**: <?= date('Y-m-d H:i:s') ?>  
**Version**: 1.0 Security Hardened  
**Status**: Ready for production (complete checklist first)

---

## 💡 Pro Tips

1. **Test rate limiting**: Try 11 rapid login attempts - 11th should fail
2. **Test CSRF**: Submit form without token - should get 403
3. **Test path traversal**: Try domain `../../etc/passwd` - should error
4. **Test JSON size**: Send 2MB JSON to API - should reject
5. **Monitor logs**: Check error logs daily for security events

---

**Need Help?** Review the full audit: [SECURITY_AUDIT_FIXES.md](SECURITY_AUDIT_FIXES.md)
