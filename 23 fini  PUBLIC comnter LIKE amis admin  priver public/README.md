# Mohamed Aroussi Video Sharing Platform

A modern video sharing platform built with PHP, MySQL, and Bootstrap.

## Features

- User registration and authentication
- Video uploading and sharing
- Stories feature (similar to Instagram/TikTok)
- Like and comment system
- User profiles
- Dark/light theme toggle
- Responsive design for all devices

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server with mod_rewrite enabled
- XAMPP, WAMP, MAMP, or similar local development environment

## Installation

1. Clone the repository to your web server's document root:
   ```
   git clone https://github.com/yourusername/video-platform.git
   ```

2. Import the database schema:
   - Create a new MySQL database named `tutorial`
   - Import the `tutorial.sql` file into your database

3. Configure the database connection:
   - Open `config.php` and update the database credentials if needed

4. Set proper permissions:
   - Ensure the `videos` and `uploads` directories are writable by the web server

5. Access the website:
   - Navigate to `http://localhost/ba/` in your web browser

## Directory Structure

- `css/` - CSS stylesheets
- `js/` - JavaScript files
- `includes/` - Reusable PHP components
- `videos/` - Uploaded video files
- `uploads/` - Uploaded images and other files

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Credits

- [Bootstrap](https://getbootstrap.com/)
- [Font Awesome](https://fontawesome.com/)
- [jQuery](https://jquery.com/)

## Contact

For any questions or suggestions, please contact:
- Email: contact@mohamedaroussi.com
- Website: [mohamedaroussi.com](https://mohamedaroussi.com)
