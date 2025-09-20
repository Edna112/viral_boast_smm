# cPanel Configuration Guide for Viral Boast SMM

## Database Configuration

Since cPanel uses MySQL, you need to create a `.env` file in your project root with the following configuration:

### .env File Content

```env
APP_NAME="Viral Boast SMM"
APP_ENV=production
APP_KEY=base64:your-app-key-here
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Database Configuration for cPanel MySQL
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Session and Cache Configuration (use file for cPanel)
SESSION_DRIVER=file
CACHE_STORE=file

# Mail Configuration (for verification emails)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"

# Twilio SMS Configuration (for phone verification)
TWILIO_SID=your_twilio_sid
TWILIO_TOKEN=your_twilio_token
TWILIO_FROM=your_twilio_phone_number

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,your-domain.com
```

## Steps to Configure

1. **Create .env file** in your project root with the above content
2. **Replace the following values** with your actual cPanel details:
   - `your_database_name` - Your MySQL database name from cPanel
   - `your_username` - Your MySQL username from cPanel
   - `your_password` - Your MySQL password from cPanel
   - `your-domain.com` - Your actual domain name
   - `your-app-key-here` - Generate with: `php artisan key:generate`

3. **Generate Application Key**:
   ```bash
   php artisan key:generate
   ```

4. **Run Migrations**:
   ```bash
   php artisan migrate
   ```

## Important Notes

- **Session Driver**: Set to `file` to avoid MySQL connection issues
- **Cache Store**: Set to `file` to avoid MySQL connection issues
- **Database**: Uses MySQL as configured in cPanel
- **Primary Key**: Fixed to use `uuid` instead of `id` for users table

## Fixed Issues

✅ **Primary Key Mismatch**: Updated users table migration to use `uuid` as primary key
✅ **Database Configuration**: Set default to MySQL for cPanel compatibility
✅ **Session/Cache**: Configured to use file driver to avoid MySQL connection issues
✅ **User Model**: All fields properly configured for the updated migration

## Testing

After configuration, test the connection with:
```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

This should return a PDO object if the connection is successful.








