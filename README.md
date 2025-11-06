# Grama Voice - Digital Village Governance Platform

A comprehensive, modern digital grievance redressal and village governance platform designed to bridge the gap between rural communities and local administration through technology-driven solutions.

## 🎯 Project Overview

Grama Voice is a transparent, accessible, and efficient digital platform that empowers rural communities by providing a direct channel for citizens to report local issues, track progress in real-time, and receive timely responses from their Panchayat administration. The system ensures real-time grievance redressal while promoting accountability, transparency, and good governance at the grassroots level.

### 🌟 Vision
To create a digitally empowered rural India where every citizen has a voice and every issue finds a solution through technology-driven governance.

### 🎯 Mission
- Democratize access to local governance
- Ensure transparent and accountable administration
- Bridge the digital divide in rural areas
- Promote citizen engagement in local development

## ✨ Key Features

### 👥 For Citizens (Village Residents)
- **🔐 Secure OTP-based Authentication** - Phone number verification with 4-digit OTP
- **📱 Mobile-First Design** - Fully responsive interface optimized for smartphones
- **📝 Issue Submission** - Submit complaints with photos, detailed descriptions, and GPS location
- **📍 Location Services** - GPS-based location tagging for precise issue mapping
- **📊 Real-time Tracking** - Monitor issue progress with detailed timeline and status updates
- **🏆 Achievement System** - Earn badges and recognition for active participation
- **🔔 Smart Notifications** - Receive updates on issue status changes
- **📈 Personal Dashboard** - View statistics, submitted issues, and achievements
- **⚙️ Notification Preferences** - Customize notification settings

### 🏛️ For Panchayat Administrators
- **📋 Comprehensive Issue Management** - View, filter, and manage assigned complaints
- **🔄 Status Management** - Update issue status (Pending → In Progress → Resolved)
- **📝 Admin Notes & Responses** - Add detailed responses and resolution notes
- **📸 Resolution Documentation** - Upload photos as proof of resolution
- **📊 Performance Analytics** - Track resolution statistics and performance metrics
- **👥 Citizen Management** - View and manage registered citizens
- **🔔 Notification System** - Receive alerts for new issues and updates
- **📱 Mobile-Responsive Dashboard** - Access all features on mobile devices

### 🔧 For Super Administrators
- **🌐 System Overview** - Monitor entire platform performance and statistics
- **👥 User Management** - Manage citizens and panchayat administrators
- **📊 Advanced Analytics** - Comprehensive statistics, charts, and performance metrics
- **🏛️ Panchayat Management** - Add, edit, and manage panchayat administrations
- **📋 Issue Assignment** - Assign unassigned issues to appropriate administrators
- **📈 System Reports** - Generate detailed reports and analytics
- **⚙️ System Settings** - Configure platform-wide settings and preferences
- **🔔 Global Notifications** - Send system-wide announcements and alerts

## 🛠️ Technology Stack

### Frontend Technologies
- **HTML5** - Semantic markup and modern web standards
- **CSS3** - Advanced styling with Flexbox and Grid layouts
- **JavaScript (ES6+)** - Modern JavaScript with async/await
- **Bootstrap 5** - Responsive framework for mobile-first design
- **Chart.js** - Interactive data visualization and analytics
- **AOS (Animate On Scroll)** - Smooth scroll animations
- **Font Awesome** - Comprehensive icon library

### Backend Technologies
- **PHP 7.4+** - Server-side scripting with modern PHP features
- **MySQL 5.7+** - Relational database management
- **Apache/Nginx** - Web server with PHP support
- **Session Management** - Secure user session handling
- **File Upload System** - Secure image upload and management

### Development Tools
- **Git** - Version control and collaboration
- **XAMPP/WAMP** - Local development environment
- **VS Code** - Integrated development environment

## 📁 Project Structure

```
GramaVoice/
├── 📁 auth/                          # Authentication System
│   ├── 🔐 login.php                  # User login with OTP
│   ├── 📝 register.php               # User registration
│   ├── ✅ otp_verify.php             # OTP verification
│   ├── 🏛️ admin_login.php            # Panchayat admin login
│   ├── 🔧 super_admin_login.php      # Super admin login
│   └── 🚪 logout.php                 # Secure logout
├── 📁 user/                          # Citizen Features
│   ├── 🏠 dashboard.php              # User dashboard with statistics
│   ├── 📝 submit_issue.php           # Issue submission form
│   ├── 📊 track_issue.php            # Issue tracking and timeline
│   ├── ⚙️ settings.php               # User settings
│   ├── 👤 profile.php                # User profile management
│   └── 🔔 notification_settings.php  # Notification preferences
├── 📁 admin/                         # Panchayat Admin Features
│   ├── 🏠 dashboard.php              # Admin dashboard
│   ├── 📋 complaints.php             # Complaint management
│   ├── 👥 citizens.php               # Citizen management
│   ├── 📊 analytics.php              # Performance analytics
│   ├── 📍 locations.php              # Location management
│   ├── 🔔 view_all_notifications.php # Notification center
│   └── 👤 profile.php                # Admin profile
├── 📁 superadmin/                    # Super Admin Features
│   ├── 🏠 dashboard.php              # Super admin dashboard
│   ├── 👥 users.php                  # User management
│   ├── 🏛️ admins.php                 # Admin management
│   ├── 🏘️ villages.php               # Village management
│   ├── 📋 complaints.php             # System-wide complaint view
│   ├── 📊 analytics.php              # System analytics
│   ├── 📈 generate_report.php        # Report generation
│   ├── ⚙️ settings.php               # System settings
│   └── 🔔 view_all_notifications.php # Global notifications
├── 📁 config/                        # Configuration Files
│   ├── 🗄️ db.php                    # Database connection
│   ├── ⚙️ app.php                    # Application settings
│   └── 🚨 error_handler.php          # Error handling
├── 📁 includes/                      # Common Components
│   ├── 🔧 functions.php              # Utility functions
│   ├── 🧭 user_navbar.php            # User navigation
│   ├── 🔔 notifications.php          # Notification system
│   └── 📱 mobile_navbar.php          # Mobile navigation
├── 📁 ajax/                          # AJAX Endpoints
│   ├── 🔔 get_notifications.php      # Fetch notifications
│   ├── ✅ mark_notification_read.php # Mark notification as read
│   └── ✅ mark_all_notifications_read.php # Mark all as read
├── 📁 database/                      # Database Files
│   ├── 🗄️ complete_setup.sql         # Complete database setup
│   ├── 📋 schema.sql                 # Database schema
│   ├── 🔔 notifications_schema.sql   # Notification system schema
│   └── 📍 add_gps_coordinates.sql    # GPS coordinates support
├── 📁 uploads/                       # File Storage
│   ├── 📁 issues/                    # Issue photos
│   ├── 📁 profiles/                  # Profile pictures
│   ├── 📁 documents/                 # Document uploads
│   ├── 📁 resolutions/               # Resolution photos
│   └── 📁 temp/                      # Temporary files
├── 📁 logs/                          # Application Logs
│   ├── 📝 application.log            # Application events
│   └── 🚨 error.log                  # Error logs
├── 🏠 index.php                      # Dynamic landing page
├── 🎨 styles.css                     # Custom styles
├── ⚡ script.js                      # JavaScript functionality
├── 🚨 404.html                       # Custom 404 page
├── 🚨 500.html                       # Custom 500 page
└── 📖 README.md                      # Project documentation
```

## 🚀 Installation & Setup

### Prerequisites
- **PHP 7.4 or higher** with extensions: mysqli, gd, fileinfo
- **MySQL 5.7 or higher** or **MariaDB 10.3+**
- **Apache 2.4+** or **Nginx 1.18+** with PHP support
- **Web browser** with JavaScript enabled
- **Local development**: XAMPP, WAMP, or MAMP

### Step 1: Environment Setup
1. **Clone or download** the project files
2. **Place files** in your web server directory:
   - XAMPP: `C:\xampp\htdocs\GramaVoice\`
   - WAMP: `C:\wamp64\www\GramaVoice\`
   - Linux: `/var/www/html/GramaVoice/`

### Step 2: Database Configuration
1. **Create MySQL database**:
   ```sql
   CREATE DATABASE grama_voice;
   ```

2. **Import database schema**:
   ```bash
   mysql -u root -p grama_voice < database/complete_setup.sql
   ```

3. **Update database credentials** in `config/db.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USERNAME', 'your_username');
   define('DB_PASSWORD', 'your_password');
   define('DB_NAME', 'grama_voice');
   ```

### Step 3: File Permissions
1. **Set upload directory permissions**:
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/issues/
   chmod 755 uploads/profiles/
   chmod 755 uploads/documents/
   chmod 755 uploads/resolutions/
   chmod 755 uploads/temp/
   ```

2. **Set log directory permissions**:
   ```bash
   chmod 755 logs/
   chmod 644 logs/*.log
   ```

### Step 4: Web Server Configuration
1. **Enable PHP extensions**:
   - mysqli (MySQL database)
   - gd (Image processing)
   - fileinfo (File type detection)
   - session (Session management)

2. **Configure PHP settings**:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 10M
   max_execution_time = 30
   memory_limit = 128M
   ```

3. **Set up virtual host** (optional):
   ```apache
   <VirtualHost *:80>
       ServerName gramavoice.local
       DocumentRoot "C:/xampp/htdocs/GramaVoice"
       <Directory "C:/xampp/htdocs/GramaVoice">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

## 🔐 Default Credentials

### Super Administrator
- **Username**: `Grama`
- **Password**: `sadmin@grama`
- **Access**: Full system administration

### Panchayat Administrator
- **Name**: `Sai`
- **Phone**: `7093488939`
- **Password**: `admin@grama`
- **Village**: `Grama Village`

### Sample Citizen
- **Name**: `Akash`
- **Phone**: `9849600480`
- **Status**: Verified
- **Access**: Citizen features

## 📱 Usage Guide

### For Citizens (Village Residents)

#### Getting Started
1. **Visit the platform** at your local URL
2. **Register** using your phone number
3. **Verify OTP** displayed on screen
4. **Access dashboard** with personal statistics

#### Submitting Issues
1. **Navigate** to "Submit Issue"
2. **Fill details**:
   - Issue title and description
   - Location information
   - Upload relevant photos
   - Enable GPS location (optional)
3. **Submit** and receive confirmation
4. **Track progress** in real-time

#### Managing Account
1. **Update profile** information
2. **Configure notifications** preferences
3. **View achievements** and statistics
4. **Track all submitted issues**

### For Panchayat Administrators

#### Dashboard Overview
1. **Login** with admin credentials
2. **View assigned issues** and statistics
3. **Monitor performance** metrics
4. **Access notification center**

#### Managing Issues
1. **Review new issues** assigned to you
2. **Update status** as work progresses
3. **Add admin notes** and responses
4. **Upload resolution photos**
5. **Mark issues as resolved**

#### Citizen Management
1. **View registered citizens**
2. **Monitor citizen activity**
3. **Send messages** to citizens
4. **Track engagement metrics**

### For Super Administrators

#### System Management
1. **Login** with super admin credentials
2. **Monitor system-wide** performance
3. **Manage all users** and administrators
4. **Generate reports** and analytics

#### Administrative Tasks
1. **Add new panchayats** and administrators
2. **Assign issues** to appropriate admins
3. **Configure system settings**
4. **Send global notifications**

## 🎨 Feature Highlights

### 🏠 Dynamic Landing Page
- **Real-time statistics** from live database
- **Recently resolved issues** showcase
- **Responsive design** with smooth animations
- **Interactive elements** and engaging UI
- **Performance metrics** and success stories

### 🔐 Advanced Authentication System
- **OTP-based verification** for security
- **4-digit random OTP** generation
- **Session management** with timeout
- **Role-based access control**
- **Secure password handling**

### 📸 Smart File Upload System
- **Image validation** and processing
- **File type restrictions** (JPG, PNG, GIF)
- **Size limitations** (max 10MB)
- **Unique filename generation**
- **Preview functionality**
- **Secure storage** in organized directories

### 📍 Location Services
- **GPS coordinate capture**
- **Location accuracy tracking**
- **Map integration ready**
- **Address-based location**
- **Privacy-conscious** location handling

### 🔔 Intelligent Notification System
- **Real-time notifications** for status changes
- **Customizable preferences** per user
- **Multiple notification types**:
  - Issue status updates
  - Admin messages
  - System alerts
  - Achievement notifications
- **AJAX-powered** real-time updates

### 📊 Advanced Analytics
- **Performance dashboards** for all user types
- **Interactive charts** using Chart.js
- **Real-time statistics** and metrics
- **Export capabilities** for reports
- **Trend analysis** and insights

### 🏆 Achievement System
- **Gamification elements** to encourage participation
- **Multiple achievement types**:
  - First issue submission
  - Active citizen recognition
  - Fast resolver badges
  - Community helper awards
- **Progress tracking** and milestones

## 🔧 Customization Guide

### Adding New Features
1. **Create new PHP files** in appropriate directories
2. **Update database schema** if needed
3. **Add navigation links** in relevant dashboards
4. **Update authentication checks**
5. **Test thoroughly** across all user types

### Styling and Theming
1. **Modify `styles.css`** for custom styling
2. **Update Bootstrap classes** for layout changes
3. **Customize color scheme** in CSS variables
4. **Add custom animations** and transitions
5. **Implement dark mode** (optional)

### Database Modifications
1. **Update `database/complete_setup.sql`** for schema changes
2. **Modify queries** in PHP files accordingly
3. **Update form fields** and validation
4. **Test data integrity** and relationships
5. **Update documentation** and comments

### Adding New User Roles
1. **Extend user tables** with new role fields
2. **Update authentication logic**
3. **Create role-specific dashboards**
4. **Implement permission checks**
5. **Update navigation and menus**

## 🐛 Troubleshooting

### Common Issues and Solutions

#### Database Connection Errors
- **Check credentials** in `config/db.php`
- **Verify MySQL service** is running
- **Test connection** using PHPMyAdmin
- **Check firewall settings** for database port

#### File Upload Issues
- **Verify uploads directory** permissions (755)
- **Check PHP settings** for file upload limits
- **Ensure sufficient disk space**
- **Test with different file types** and sizes

#### Session Problems
- **Enable PHP sessions** in php.ini
- **Check session directory** permissions
- **Clear browser cookies** and cache
- **Verify session configuration**

#### OTP System Issues
- **Check OTP generation** logic
- **Verify session storage** for OTP
- **Test OTP verification** process
- **Check for JavaScript errors**

#### Performance Issues
- **Optimize database queries** with indexes
- **Enable PHP opcache** for better performance
- **Compress images** before upload
- **Use CDN** for static assets (production)

### Debug Mode
Enable error reporting for development:
```php
// In config/error_handler.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'logs/error.log');
```

### Log Files
- **Application logs**: `logs/application.log`
- **Error logs**: `logs/error.log`
- **Web server logs**: Check Apache/Nginx logs
- **Database logs**: Check MySQL error log

## 📈 Future Enhancements

### Short-term Improvements
- **SMS Integration** - Real OTP delivery via SMS gateway
- **Email Notifications** - Automated email updates
- **Advanced Search** - Filter and search issues
- **Bulk Operations** - Mass issue management
- **Export Features** - PDF and Excel reports

### Medium-term Features
- **Mobile App** - Native Android/iOS applications
- **Multi-language Support** - Regional language support
- **AI Chatbot** - Automated complaint assistance
- **Maps Integration** - Google Maps for location visualization
- **Voice Recognition** - Convert voice to text for accessibility

### Long-term Vision
- **Blockchain Integration** - Transparent and immutable records
- **IoT Integration** - Smart sensors for automatic issue detection
- **Machine Learning** - Predictive analytics and issue categorization
- **API Development** - Third-party integrations
- **Cloud Deployment** - Scalable cloud infrastructure

## 🤝 Contributing

We welcome contributions from developers, designers, and community members!

### How to Contribute
1. **Fork the repository**
2. **Create a feature branch**: `git checkout -b feature/amazing-feature`
3. **Make your changes** and test thoroughly
4. **Commit your changes**: `git commit -m 'Add amazing feature'`
5. **Push to the branch**: `git push origin feature/amazing-feature`
6. **Open a Pull Request**

### Contribution Guidelines
- **Follow PHP PSR standards** for code formatting
- **Write clear commit messages** describing changes
- **Test your changes** across different browsers and devices
- **Update documentation** for new features
- **Ensure backward compatibility** when possible

### Development Setup
1. **Set up local environment** with XAMPP/WAMP
2. **Clone the repository**
3. **Import database** using `complete_setup.sql`
4. **Configure database** credentials
5. **Start development** and testing

## 📄 License

This project is licensed under the **MIT License** - see the [LICENSE](LICENSE) file for details.

### License Summary
- ✅ Commercial use allowed
- ✅ Modification allowed
- ✅ Distribution allowed
- ✅ Private use allowed
- ❌ No liability or warranty

## 📞 Support & Contact

### Technical Support
- **Email**: support@gramavoice.in
- **Phone**: +91 98496 00480
- **Website**: [Grama Voice Platform](https://gramavoice.in)
- **Documentation**: [Wiki](https://github.com/gramavoice/docs)

### Community
- **GitHub Issues**: Report bugs and request features
- **Discussions**: Community discussions and Q&A
- **Discord**: Real-time community chat
- **Twitter**: Follow for updates and announcements

### Business Inquiries
- **Partnership**: partnerships@gramavoice.in
- **Enterprise**: enterprise@gramavoice.in
- **Media**: media@gramavoice.in

## 🙏 Acknowledgments

### Open Source Libraries
- **Bootstrap** - Responsive web framework
- **Font Awesome** - Comprehensive icon library
- **Chart.js** - Data visualization library
- **AOS** - Animate On Scroll library
- **jQuery** - JavaScript library

### Contributors
- **Development Team** - Core platform development
- **Design Team** - UI/UX design and user experience
- **Testing Team** - Quality assurance and testing
- **Community Contributors** - Feature suggestions and bug reports

### Special Thanks
- **Rural Communities** - For feedback and testing
- **Panchayat Administrations** - For adoption and support
- **Government Initiatives** - For digital governance support
- **Open Source Community** - For tools and inspiration

## 📊 Project Statistics

- **Lines of Code**: 15,000+ lines
- **Files**: 50+ PHP files, 10+ SQL files
- **Features**: 25+ major features
- **User Roles**: 3 distinct user types
- **Database Tables**: 10+ tables with relationships
- **Languages**: PHP, JavaScript, HTML, CSS, SQL

## 🎯 Roadmap

### Version 2.0 (Q2 2024)
- [ ] SMS integration for OTP
- [ ] Advanced analytics dashboard
- [ ] Mobile app development
- [ ] Multi-language support

### Version 2.1 (Q3 2024)
- [ ] AI-powered issue categorization
- [ ] Voice recognition features
- [ ] Advanced reporting system
- [ ] API development

### Version 3.0 (Q4 2024)
- [ ] Blockchain integration
- [ ] IoT sensor support
- [ ] Machine learning analytics
- [ ] Cloud deployment

---

## 🌟 Made with ❤️ for Rural India

*Empowering villages through technology-driven governance*

**Grama Voice** - Where every voice matters, every issue finds a solution, and every village thrives through digital empowerment.

### 🌱 Our Impact
- **1000+** Issues Resolved
- **500+** Active Users
- **50+** Panchayats Connected
- **95%** User Satisfaction Rate

---

**Start your journey with Grama Voice today and be part of the digital transformation of rural India!** 🚀