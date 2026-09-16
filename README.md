# Argo Books Website

## Introduction

This is the website for [Argo Books](https://github.com/ArgoRobots/Argo-Books-Avalonia), accounting software with receipt scanning, predictive analytics, inventory management, and more. This website serves as a platform for users to download the software, purchase license keys, access documentation, and has an administrative system for managing licenses, user accounts, and viewing analytics.

You can view the live website here: www.argorobots.com.

## Technologies Used

### Frontend:

- **HTML5 and CSS3**: Structure and styling
- **JavaScript and jQuery**: Interactive elements and dynamic content loading
- **Chart.js**: Data visualization for analytics dashboard

### Backend:

- **PHP**: Server-side processing
- **MySQL**: Database for storing licenses, user accounts, and analytics data
- **Two-factor authentication (TOTP)**: Enhanced security for admin access

## Core Features

### Public Website

- Product information and marketing pages
- Free version download
- License key purchase system
- Comprehensive documentation
- Community page for feature requests and bug reports
- Support/contact system
- About us and legal information

### Admin System

- Secure admin dashboard with two-factor authentication
- License key generation and management
- User account administration
- Statistics tracking and analytics dashboard

## Getting Started

To run the site on your own machine (Windows with Laragon, or macOS with Homebrew), follow [Local development setup](read-me/setup/Local%20development%20setup.md).

## Documentation

Reference docs live in [read-me/](read-me/).

### Operations

| Document | Read it when |
|---|---|
| [Deployment](read-me/Deployment.md) | Shipping to production, or a push did not land on the server |
| [Cron jobs](read-me/Cron%20jobs.md) | Adding a scheduled task, or one has stopped running |
| [Admin guide](read-me/Admin%20guide.md) | Checking payment processor fees, switching between sandbox and production, rotating payment keys, or creating an admin account |
| [Refund block response procedure](read-me/procedures/Refund%20block%20response%20procedure.md) | The refund system has hard-blocked someone and emailed you |

### Setup

| Document | Read it when |
|---|---|
| [Local development setup](read-me/setup/Local%20development%20setup.md) | Installing and running the site on Windows (Laragon) or macOS (Homebrew) |
| [Local email setup](read-me/setup/Local%20email%20setup.md) | Setting up MailHog so local mail does not try to reach a real server |
| [Payment provider setup](read-me/setup/Payment%20provider%20setup.md) | Configuring Stripe, PayPal or Square, for the portal or subscriptions |
| [Cloudflare Turnstile setup](read-me/setup/Cloudflare%20Turnstile%20setup.md) | Working on the free receipt scanner's bot protection |
| [Google Ads campaign setup](read-me/setup/Google%20Ads%20campaign%20setup.md) | Building a new Google Ads campaign, step by step |

### Testing

| Document | Read it when |
|---|---|
| [Payment provider testing](read-me/testing/Payment%20provider%20testing.md) | Running sandbox payments through any provider |
| [First-run install tracking](read-me/testing/First-run%20install%20tracking.md) | Checking that installs attribute back to the originating ad click |
| [PHPUnit suite](tests/README.md) | Running or adding backend tests. Covers the financial and licensing flows |

### Marketing

| Document | Read it when |
|---|---|
| [Email outreach](read-me/Email%20outreach.md) | Running or changing the outreach pipeline |
| [Google Ads economics](read-me/Google%20Ads%20economics.md) | Deciding whether to spend, and what a click is worth. Keep the figures current |
