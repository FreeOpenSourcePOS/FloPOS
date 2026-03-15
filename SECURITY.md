# Security Policy

## Supported Versions

We actively support and provide security updates for the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |

## Reporting a Vulnerability

We take security vulnerabilities seriously. If you discover a security issue, please report it responsibly.

### How to Report

**Do NOT report security vulnerabilities through public GitHub issues.**

Instead, please email us directly at: **security@flopos.com**

Please include the following information:
- Type of vulnerability
- Full paths of source file(s) related to the vulnerability
- Location of the affected source code
- Any special configuration required to reproduce the issue
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue

### Response Timeline

- We aim to acknowledge reports within 48 hours
- We aim to provide a more detailed response within 7 days
- We will work with you to understand and resolve the issue
- Public disclosure will be made after a fix is available

## Security Best Practices

When deploying Flo POS:

### Production Deployment
- Use HTTPS only (enable SSL/TLS)
- Keep PHP, Laravel, and dependencies updated
- Use strong database passwords
- Configure proper firewall rules
- Enable rate limiting on login endpoints
- Regular backups of database

### Server Requirements
- PHP 8.4+ with secure configuration
- PostgreSQL with encrypted connections
- Redis with authentication
- Regular security updates

### Application Security
- Change default application keys
- Use environment variables for secrets
- Enable debug mode only in development
- Review user permissions regularly

## Scope

This security policy covers:
- The Flo POS core application
- Backend API (Laravel)
- Frontend (Next.js)

Third-party services and libraries are covered by their respective security policies.

---

**Thank you for helping keep Flo POS secure!**
