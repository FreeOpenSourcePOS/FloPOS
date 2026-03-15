# Contributing to Flo POS

Thank you for your interest in contributing to Flo POS! This document outlines how you can help make this open-source POS system available to every small business.

## 🤝 Our Vision

Flo POS exists to provide free, professional-grade Point of Sale software to millions of small businesses in India and Southeast Asia who cannot afford expensive commercial solutions. Every contribution—whether code, documentation, or simply spreading the word—helps achieve this goal.

## 🚀 Ways to Contribute

### 1. 🐛 Bug Reports & Issues
- Search existing issues first
- Use the issue template
- Include steps to reproduce
- Mention your environment (OS, browser, PHP version, etc.)

### 2. 💡 Feature Requests
- Describe the problem you're trying to solve
- Propose a solution
- Consider impact on existing features

### 3. 🛠️ Code Contributions

#### Getting Started

```bash
# Fork the repository
# Clone your fork
git clone https://github.com/YOUR_USERNAME/FloPOS.git
cd FloPOS

# Add upstream remote
git remote add upstream https://github.com/FreeOpenSourcePOS/FloPOS.git

# Create a branch for your feature
git checkout -b feature/amazing-new-feature
```

#### Development Setup

```bash
# Backend
cd backend
cp .env.example .env
composer install
php artisan key:generate

# Create databases
# Run migrations for main DB and at least one tenant DB for testing

# Frontend
cd ../frontend
cp .env.example .env.local
pnpm install
pnpm dev
```

#### Coding Standards

**PHP (Laravel)**
- Follow Laravel coding style
- Use Pest or PHPUnit for tests
- Run `composer pint` before committing

**TypeScript/React**
- Follow ESLint rules
- Use TypeScript strictly
- Run `pnpm lint` and `pnpm typecheck` before committing

```bash
# Frontend checks
cd frontend
pnpm lint        # Fix linting issues
pnpm typecheck   # TypeScript errors

# Backend checks
cd backend
composer pint    # Code style
php artisan test
```

#### Submitting Pull Requests

1. **Keep PRs small and focused** - One feature or fix per PR
2. **Update tests** - Include tests for new features
3. **Update documentation** - Keep docs in sync with code
4. **Use conventional commits**:
   - `feat: add new feature`
   - `fix: resolve bug`
   - `docs: update documentation`
   - `refactor: code restructuring`

5. **PR Template**:
```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
Describe testing performed

## Screenshots (if applicable)
```

### 4. 🧪 Testing

```bash
# Backend tests
cd backend
php artisan test

# Frontend tests
cd frontend
pnpm test
```

### 5. 📖 Documentation

- Fix typos and unclear explanations
- Add examples and tutorials
- Translate to local languages
- Improve API documentation

### 6. 🌐 Translations

Help us support more languages:
- UI text translations
- Number/currency formatting
- Date/time localization

### 7. ⭐ Star & Share

The simplest way to help:
- ⭐ Star the repo
- 📢 Share with developers and business owners
- 📝 Write about your experience

## 💬 Community

- **Discord**: https://discord.gg/flopos
- **Telegram**: https://t.me/flopos
- **GitHub Discussions**: https://github.com/FreeOpenSourcePOS/FloPOS/discussions

## 📋 Issue Templates

### Bug Report
```markdown
**Describe the bug**
A clear description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '....'
3. See error

**Expected behavior**
What you expected to happen.

**Screenshots**
If applicable, add screenshots.

**Environment:**
- OS: [e.g., Ubuntu 24.04]
- Browser: [e.g., Chrome 120]
- PHP: [e.g., 8.4]
```

### Feature Request
```markdown
**Is your feature request related to a problem?**
A clear description of what the problem is.

**Describe the solution you'd like**
A clear description of what you want to happen.

**Describe alternatives you've considered**
Any alternative solutions you've considered.

**Additional context**
Add any other context about the feature request.
```

## 🔐 Security Vulnerabilities

For security issues, please email **dev@flopos.com** directly instead of opening a public issue.

## 📝 License

By contributing to Flo POS, you agree that your contributions will be licensed under the MIT License.

---

**Thank you for helping bring professional POS software to every small business! 🏪**
