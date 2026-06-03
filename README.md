# Carbon Footprint Analyzer

A web-based application designed to calculate, track, and analyze personal and organizational carbon footprints. Make informed decisions to reduce environmental impact with an intuitive, interactive dashboard.

## Features

- **Carbon Emission Calculation**: Calculate emissions from various activities including transportation, energy consumption, and lifestyle choices
- **Activity Tracking**: Log daily activities and automatically compute associated carbon emissions
- **Data Visualization**: Interactive charts and graphs using Chart.js to visualize carbon footprint trends over time
- **Comparisons**: Compare your carbon footprint against average benchmarks
- **Recommendations**: Receive personalized suggestions to reduce your carbon footprint
- **Reporting**: Generate detailed reports for personal or organizational use
- **Responsive Design**: Access via desktop and mobile browsers with Bootstrap-based responsive UI

## Tech Stack

- **Frontend**: HTML, CSS, JavaScript, Bootstrap, Chart.js
- **Backend**: PHP
- **Server**: Apache (via Laragon)
- **Database**: MySQL
- **Browser**: Google Chrome (recommended)
- **Development Environment**: Visual Studio Code

## Installation

### Prerequisites

- [Laragon](https://laragon.org/) installed and running
- MySQL database (included with Laragon)
- Google Chrome or any modern web browser
- Visual Studio Code (optional, for development)

### Setup Instructions

1. **Clone the repository into Laragon's www folder**

   ```bash
   cd C:\laragon\www  # On Windows
   git clone https://github.com/abgluqman/carbon-footprint-analyzer.git
   cd carbon-footprint-analyzer
   ```

2. **Create the database**

   - Open phpMyAdmin (usually at `http://localhost/phpmyadmin`)
   - Create a new database named `carbon_footprint`
   - Import the database schema from `database/schema.sql` (if provided)

3. **Configure database connection**

   - Update `config.php` with your database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASSWORD', '');
     define('DB_NAME', 'carbon_footprint');
     ```

4. **Access the application**

   - Open your browser and navigate to: `http://localhost/carbon-footprint-analyzer`
   - The application should load with the dashboard

## Project Structure

```
carbon-footprint-analyzer/
├── index.php              # Main entry point
├── config.php             # Database and configuration settings
├── css/
│   ├── bootstrap.min.css  # Bootstrap framework
│   └── style.css          # Custom styles
├── js/
│   ├── bootstrap.min.js   # Bootstrap JavaScript
│   ├── chart.min.js       # Chart.js library
│   └── main.js            # Application logic
├── includes/
│   ├── db.php             # Database connection
│   ├── header.php         # Header component
│   └── footer.php         # Footer component
├── pages/
│   ├── dashboard.php      # Main dashboard
│   ├── activities.php     # Activity logging
│   ├── calculator.php     # Carbon calculator
│   ├── analytics.php      # Analytics and reports
│   └── recommendations.php # Suggestions
├── api/
│   ├── calculate.php      # API endpoint for calculations
│   ├── activities.php     # API endpoint for activities
│   └── analytics.php      # API endpoint for analytics
└── database/
    └── schema.sql         # Database schema
```

## Usage

### Logging an Activity

1. Navigate to the "Activities" section
2. Fill in the activity details (type, duration, mode, etc.)
3. Click "Calculate" to see the carbon emissions
4. Click "Save" to record the activity

### Viewing Analytics

1. Go to the "Analytics" section
2. View interactive charts showing:
   - Daily/weekly/monthly carbon emissions
   - Breakdown by activity type
   - Trends over time
3. Export reports as needed

### Using the Carbon Calculator

1. Navigate to the "Calculator" section
2. Select the activity type (transportation, energy, etc.)
3. Enter relevant details
4. Get instant carbon emission estimates

## Configuration

Edit `config.php` to customize:

```php
// Emission factors (kg CO2 per unit)
define('EMISSION_CAR', 0.21);
define('EMISSION_BUS', 0.05);
define('EMISSION_ELECTRICITY', 0.5);

// Application settings
define('APP_NAME', 'Carbon Footprint Analyzer');
define('TIMEZONE', 'UTC');
define('CURRENCY', 'USD');
```

## Database Schema

Key tables:
- `users` - User accounts
- `activities` - Logged activities
- `emission_factors` - Carbon emission coefficients
- `reports` - Generated reports

## API Endpoints

The application provides RESTful API endpoints for integration:

- `POST /api/calculate.php` - Calculate carbon emissions
- `POST /api/activities.php` - Log a new activity
- `GET /api/activities.php` - Retrieve activities
- `GET /api/analytics.php` - Get analytics data

## Development

### Opening the Project in Visual Studio Code

```bash
code .
```

### Recommended VS Code Extensions

- PHP Intelephense
- Live Server
- MySQL
- Bootstrap 5 Quick Snippets

### Making Changes

1. Edit files in VS Code
2. Save changes (Ctrl+S)
3. Refresh the browser to see updates (F5)
4. Check browser console (F12) for debugging

## Testing

1. Test the calculator with various inputs
2. Verify database entries in phpMyAdmin
3. Check responsive design on different screen sizes
4. Test all interactive charts and filters

## Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## Browser Compatibility

- Google Chrome (recommended)
- Firefox
- Safari
- Edge

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For issues, questions, or suggestions:

- Open an [Issue](https://github.com/abgluqman/carbon-footprint-analyzer/issues)
- Check our [Documentation](./docs)
- Contact us via email or discussions

## Roadmap

- [ ] User authentication and profiles
- [ ] Advanced analytics and predictions
- [ ] Community challenges and gamification
- [ ] Real-time sustainability impact dashboard
- [ ] Carbon offset marketplace integration
- [ ] Mobile app version
- [ ] Email notifications and reminders

## Acknowledgments

- Environmental data sources: EPA, IPCC
- Chart.js for interactive visualizations
- Bootstrap for responsive UI
- Community contributors and maintainers

---

**Let's work together to reduce our carbon footprint and build a sustainable future!** 🌍♻️
