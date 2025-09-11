# Twilio SMS Setup Guide

## Environment Variables

Add these variables to your `.env` file:

```env
# Twilio Configuration
TWILIO_SID=your_twilio_account_sid
TWILIO_TOKEN=your_twilio_auth_token
TWILIO_FROM=your_twilio_phone_number
```

## How to Get Twilio Credentials

### 1. Create Twilio Account

-   Go to [https://www.twilio.com](https://www.twilio.com)
-   Sign up for a free account
-   Verify your phone number

### 2. Get Your Credentials

-   **Account SID**: Found on your Twilio Console Dashboard
-   **Auth Token**: Found on your Twilio Console Dashboard (click to reveal)
-   **Phone Number**: Purchase a phone number from Twilio Console > Phone Numbers > Manage > Buy a number

### 3. Example Configuration

```env
TWILIO_SID=AC1234567890abcdef1234567890abcdef
TWILIO_TOKEN=your_auth_token_here
TWILIO_FROM=+1234567890
```

## Testing

### Development Mode

If Twilio credentials are not configured, the system will:

-   Log SMS messages to Laravel logs instead of sending them
-   Continue to work for testing purposes

### Production Mode

With proper Twilio configuration:

-   SMS messages will be sent to actual phone numbers
-   All SMS activity will be logged
-   Error handling for failed SMS delivery

## Phone Number Format

-   Use international format: `+1234567890`
-   Include country code
-   No spaces or special characters

## Cost

-   Twilio charges per SMS sent
-   Free trial includes $15 credit
-   Check Twilio pricing for current rates






