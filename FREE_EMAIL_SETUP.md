# Free Email Setup Guide

## Option 1: Gmail SMTP (Recommended)

### Setup Steps:

1. **Enable 2-Factor Authentication** on your Gmail account
2. **Generate App Password**:
    - Go to Google Account Settings
    - Security → 2-Step Verification → App passwords
    - Generate password for "Mail"

### Environment Configuration:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="Viral Boast SMM"
```

### Limits:

-   **500 emails/day** (free Gmail account)
-   **2,000 emails/day** (Google Workspace)

---

## Option 2: Outlook/Hotmail SMTP (Free)

### Environment Configuration:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-mail.outlook.com
MAIL_PORT=587
MAIL_USERNAME=your-email@outlook.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@outlook.com
MAIL_FROM_NAME="Viral Boast SMM"
```

### Limits:

-   **300 emails/day** (free Outlook account)

---

## Option 3: SendGrid (Free Tier)

### Setup Steps:

1. Sign up at [sendgrid.com](https://sendgrid.com)
2. Verify your account
3. Get API key from Settings → API Keys

### Environment Configuration:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Viral Boast SMM"
```

### Limits:

-   **100 emails/day** (free tier)
-   **40,000 emails/month** (free tier)

---

## Option 4: Mailtrap (Free for Testing)

### For Development/Testing Only:

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=test@example.com
MAIL_FROM_NAME="Viral Boast SMM"
```

### Limits:

-   **100 emails/month** (free tier)
-   **Perfect for testing** (emails don't actually send)

---

## Recommended Setup for Production

### Gmail SMTP (Best Free Option):

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-business-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-business-email@gmail.com
MAIL_FROM_NAME="Viral Boast SMM"
```

### Benefits:

-   ✅ **Free** (500 emails/day)
-   ✅ **Reliable** (Google infrastructure)
-   ✅ **Easy setup**
-   ✅ **Professional appearance**
-   ✅ **Good deliverability**

### Upgrade Path:

-   Start with Gmail SMTP
-   Upgrade to Google Workspace ($6/month) for 2,000 emails/day
-   Or switch to SendGrid when you need more volume






