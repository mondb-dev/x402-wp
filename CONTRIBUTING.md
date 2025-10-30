# Contributing to X402 Paywall

Thank you for your interest in contributing to the X402 Paywall WordPress plugin! This document provides guidelines and instructions for contributing.

## Code of Conduct

Please be respectful and constructive in all interactions. We're building this for the community.

## How to Contribute

### Reporting Bugs

If you find a bug, please create an issue on GitHub with:
- A clear, descriptive title
- Steps to reproduce the bug
- Expected behavior
- Actual behavior
- WordPress version, PHP version, and plugin version
- Any relevant error messages or logs

### Suggesting Features

Feature suggestions are welcome! Please create an issue with:
- A clear description of the feature
- Why it would be useful
- How it might work

### Pull Requests

1. **Fork the repository** and create your branch from `main`
2. **Follow WordPress coding standards**
3. **Add tests** for new features if applicable
4. **Update documentation** as needed
5. **Test thoroughly** before submitting

#### WordPress Coding Standards

This project follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/):

- Use tabs for indentation
- Use meaningful variable and function names
- Add inline documentation for functions
- Sanitize all inputs, escape all outputs
- Use nonces for form submissions
- Follow security best practices

#### PHP Requirements

- PHP 8.1 or higher
- Strict types where possible
- Type hints for parameters and return values

#### Security

- Never commit sensitive data (API keys, passwords, etc.)
- Validate and sanitize all user inputs
- Use WordPress nonces for CSRF protection
- Follow the principle of least privilege for capabilities

### Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/mondb-dev/x402-wp.git
   cd x402-wp
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Set up a local WordPress development environment
4. Symlink or copy the plugin to your WordPress plugins directory
5. Activate the plugin in WordPress

### Testing

- Test on a clean WordPress installation
- Test with different PHP versions (8.1, 8.2, 8.3)
- Test with different WordPress versions (6.0+)
- Test both EVM and Solana payment flows
- Test with different user roles (admin, editor, author)

### Documentation

Please update documentation when:
- Adding new features
- Changing existing functionality
- Fixing bugs that affect usage

Documentation files to update:
- `README.md` - Main documentation
- `INSTALLATION.md` - Installation instructions
- Inline code comments
- Plugin settings help text

## Questions?

If you have questions about contributing, feel free to:
- Open an issue for discussion
- Check existing issues and pull requests
- Review the [X402 protocol documentation](https://x402.gitbook.io/x402)

## License

By contributing, you agree that your contributions will be licensed under the Apache 2.0 License.
