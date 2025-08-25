# Sun Trading Company - Server Deployment Guide
**Developed by Ahmed Elnakieb | Email: ahmedelnakieb95@gmail.com**

## 📋 Pre-Deployment Checklist

### Files to Upload
Upload ALL files and directories from your local project to your web server:

```
sun_website/
├── admin/                  # Admin panel (Full directory)
├── backups/               # Database backups directory
├── fonts/                 # Local font files
├── images/                # Static images
├── js/                    # JavaScript files
├── packages/              # CSS/JS packages
├── uploads/               # User uploaded files
├── index.php              # Main website
├── maintenance.php        # Maintenance mode page
├── manifest.json          # PWA manifest
├── offline.html           # Offline page
├── script.js              # Main JavaScript
├── styles.css             # Main stylesheet
├── sw.js                  # Service worker
├── translations.js        # Translation system
└── translations.json      # Translation data
```

## 🗄️ Database Configuration

### 1. Create MySQL Database
```sql
-- On your hosting panel (cPanel, phpMyAdmin, etc.)
CREATE DATABASE sun_trading_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Database Import Options

#### Option A: Using Admin Panel (Recommended)
1. Upload all files to server
2. Access: `https://yourdomain.com/admin/backup.php`
3. Use "Restore Database" feature
4. Upload your latest `.sql` backup file

#### Option B: Manual Import via phpMyAdmin
1. Access phpMyAdmin on your hosting
2. Select your database
3. Go to "Import" tab
4. Upload the `.sql` file from `/backups/` directory

### 3. Database Connection Settings
Edit `/admin/config/database.php`:

```php
<?php
class Database {
    // PRODUCTION SETTINGS - Update these for your server
    private static $host = 'localhost';           // Your MySQL host
    private static $dbname = 'sun_trading_db';    // Your database name
    private static $username = 'your_db_user';    // Your database username
    private static $password = 'your_db_password'; // Your database password
    
    // ... rest remains the same
}
?>
```

## 🔧 Server Configuration Requirements

### PHP Requirements
- **PHP Version**: 7.4 or higher (8.0+ recommended)
- **Extensions Required**:
  - PDO
  - PDO_MySQL
  - JSON
  - GD (for image processing)
  - mbstring
  - fileinfo
  - zip (for backup features)

### MySQL Requirements
- **MySQL Version**: 5.7 or higher (8.0+ recommended)
- **Storage Engine**: InnoDB
- **Charset**: utf8mb4

### Directory Permissions
Set the following permissions on your server:

```bash
# Upload directories (Read/Write)
chmod 755 uploads/
chmod 755 uploads/products/
chmod 755 uploads/content/
chmod 755 uploads/general/
chmod 755 uploads/logo/

# Backup directory (Read/Write)
chmod 755 backups/

# Admin assets (Read only)
chmod 644 admin/assets/vendor/
```

## 🌐 Domain Configuration

### 1. DNS Settings
Point your domain to your server:
- **A Record**: `yourdomain.com` → `Server IP`
- **CNAME**: `www.yourdomain.com` → `yourdomain.com`

### 2. SSL Certificate (Recommended)
- Install SSL certificate through your hosting provider
- Force HTTPS redirect in .htaccess if available

### 3. .htaccess Configuration (Optional)
Create `.htaccess` in root directory:

```apache
# Redirect to HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Prevent access to sensitive files
<Files "*.sql">
    Order Allow,Deny
    Deny from all
</Files>

<Files "config.php">
    Order Allow,Deny
    Deny from all
</Files>

# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
```

## 🔐 Security Configuration

### 1. Admin Panel Security
Default admin credentials (CHANGE IMMEDIATELY):
- **Username**: `admin`
- **Password**: `admin123`

**To change admin password**:
1. Login to admin panel: `https://yourdomain.com/admin/`
2. Go to Users section
3. Edit admin user
4. Set strong password

### 2. Database Security
- Use strong database passwords
- Limit database user permissions
- Enable database SSL if available

### 3. File Upload Security
File upload restrictions are already configured:
- **Allowed types**: JPG, JPEG, PNG, GIF, PDF, DOC, DOCX
- **Max size**: 10MB per file
- **Upload directory**: `/uploads/` (outside web root recommended)

## 📧 Email Configuration

### SMTP Settings (if using contact forms)
Edit email configuration in admin panel:
1. Go to Settings → Contact
2. Configure SMTP settings:
   - **SMTP Host**: Your hosting SMTP server
   - **SMTP Port**: 587 (TLS) or 465 (SSL)
   - **Username**: Your email address
   - **Password**: Your email password

## 🔄 Post-Deployment Setup

### 1. Initial Admin Setup
1. Access: `https://yourdomain.com/admin/`
2. Login with default credentials
3. **IMMEDIATELY** change admin password
4. Update company information in Settings
5. Upload company logo
6. Configure contact information

### 2. Website Content Setup
1. Go to Content Management
2. Update all website content
3. Upload product images
4. Configure theme settings
5. Test contact forms

### 3. Backup Configuration
1. Go to Backup section in admin
2. Create initial backup
3. Download and store safely
4. Set up regular backup schedule

## 🧪 Testing Checklist

### Frontend Testing
- [ ] Homepage loads correctly
- [ ] All sections display properly
- [ ] Theme switching works
- [ ] Language switching works
- [ ] Contact forms submit
- [ ] Mobile responsiveness
- [ ] PWA features work

### Admin Panel Testing
- [ ] Login works with new credentials
- [ ] All admin pages load
- [ ] Content editing works
- [ ] Image uploads work
- [ ] User management functions
- [ ] Backup/restore works

### Database Testing
- [ ] Database connection successful
- [ ] All tables created
- [ ] Sample data imported
- [ ] CRUD operations work
- [ ] Activity logging works

## 🔍 Troubleshooting

### Common Issues

#### Database Connection Error
**Error**: "Database connection failed"
**Solution**: 
1. Check database credentials in `/admin/config/database.php`
2. Verify database exists
3. Test database connection from hosting panel

#### File Upload Errors
**Error**: "Failed to upload file"
**Solution**:
1. Check directory permissions (755)
2. Verify PHP upload limits
3. Check available disk space

#### Admin Panel Not Loading
**Error**: "Page not found" or styling issues
**Solution**:
1. Check all files uploaded correctly
2. Verify `/admin/assets/vendor/` directory exists
3. Check PHP error logs

#### SSL Certificate Issues
**Error**: "Not secure" warning
**Solution**:
1. Install SSL certificate
2. Update .htaccess for HTTPS redirect
3. Clear browser cache

## 📞 Support Information

**Developer**: Ahmed Elnakieb  
**Email**: ahmedelnakieb95@gmail.com  
**Project**: Sun Trading Company Website  

For technical support, please include:
- Server details (PHP version, hosting provider)
- Error messages (exact text)
- Steps to reproduce the issue
- Screenshots if applicable

## 🎯 Performance Optimization

### Server-Side Optimizations
- Enable GZIP compression
- Use CDN for static assets
- Enable browser caching
- Optimize database queries
- Use PHP opcache

### Database Optimizations
- Regular database cleanup
- Index optimization
- Query optimization
- Regular backups

---

**© 2024 Sun Trading Company - Developed by Ahmed Elnakieb**