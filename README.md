# Employee Attendance System

A simple employee attendance system that runs entirely on GitHub Pages. This system uses browser localStorage for data storage and supports employee registration, attendance marking, and admin management.

## Features

- ✅ Employee Registration (text-based, no face recognition)
- ✅ Attendance Marking (using Employee ID)
- ✅ Admin Panel with statistics
- ✅ Department-wise attendance tracking
- ✅ Attendance Export (CSV)
- ✅ Employee Management (Edit/Delete)
- ✅ Manual Attendance Entry
- ✅ Runs entirely on GitHub Pages (static HTML/JS/CSS)
- ✅ No server or database required
- ✅ Works offline (after initial load)

## Setup for GitHub Pages

### Option 1: Fork this repository

1. Click "Fork" on this repository
2. Go to your forked repository
3. Go to **Settings** > **Pages**
4. Under **Source**, select the branch (usually `main` or `master`)
5. Select the folder (usually `/ (root)`)
6. Click **Save**
7. Wait for GitHub to deploy (usually takes 1-2 minutes)

Your site will be available at: `https://yourusername.github.io/attendance-system/`

### Option 2: Create a new repository

1. Create a new repository on GitHub
2. Clone this repository locally
3. Remove the `.git` folder (if you want to start fresh)
4. Initialize a new git repository: `git init`
5. Add all files: `git add .`
6. Commit: `git commit -m "Initial commit"`
7. Add your remote: `git remote add origin https://github.com/yourusername/your-repo-name.git`
8. Push: `git push -u origin main`
9. Follow steps 3-7 from Option 1

## Default Admin Credentials

- **Username:** `admin`
- **Password:** `admin123`

**⚠️ Important:** Change the default admin password after first login by editing `assets/js/database.js` or through the admin panel (if you add that feature).

## How to Use

### For Employees

1. **Register:** 
   - Go to the Register page
   - Fill in your Employee ID, Full Name, Department, and Position
   - Click "Register Employee"

2. **Mark Attendance:** 
   - Go to the Attendance page
   - Enter your Employee ID
   - Click "Mark Attendance"
   - You can only mark attendance once per day

### For Administrators

1. **Login:** 
   - Go to Admin Panel
   - Login with admin credentials (admin / admin123)

2. **View Statistics:** 
   - See today's attendance overview
   - View department-wise statistics
   - Check attendance rates for today, week, and month

3. **Manage Employees:** 
   - Go to "Manage Employees" from the admin panel
   - Edit employee details
   - Delete employees (this will also delete their attendance records)

4. **Manual Entry:** 
   - Record attendance manually for any employee
   - Select employee, date, and time
   - Useful for corrections or missed entries

5. **Export Data:** 
   - Select start and end date
   - Click "Download CSV"
   - The CSV file will contain attendance data for all employees in the selected date range

## Data Storage

- Data is stored in browser's **localStorage**
- Each user's browser maintains its own database
- Data persists even after browser restart
- Data is stored locally and is not shared between browsers/devices
- To backup data, use the export feature
- To restore data, you would need to implement an import feature (or manually edit localStorage)

## File Structure

```
attendance-system/
├── index.html                  # Home page
├── register.html              # Employee registration
├── attendance.html            # Mark attendance
├── admin.html                 # Admin panel
├── admin_login.html           # Admin login
├── employee_management.html   # Manage employees
├── data.json                  # Data structure template
├── README.md                  # This file
├── assets/
│   ├── css/
│   │   └── style.css         # Styles
│   └── js/
│       ├── database.js       # Database operations (localStorage)
│       ├── auth.js           # Authentication
│       ├── utils.js          # Utility functions
│       └── script.js         # Main script
└── .gitignore                # Git ignore file
```

## Browser Compatibility

- ✅ Chrome/Edge (recommended)
- ✅ Firefox
- ✅ Safari
- ✅ Opera
- ✅ Any modern browser with JavaScript enabled

## Technical Details

- **Frontend:** HTML, CSS, JavaScript (Vanilla JS - no frameworks)
- **Storage:** Browser localStorage
- **Deployment:** GitHub Pages (static hosting)
- **No dependencies:** No npm packages, no build process required

## Limitations

- Data is stored locally in each browser
- Data is not shared between devices or browsers
- No server-side validation
- No multi-user support (each browser has its own database)
- Maximum storage limit depends on browser (usually 5-10 MB for localStorage)

## Future Enhancements

Possible improvements you could add:

- Import/Export data feature
- Change admin password functionality
- Data backup to cloud storage
- Multiple admin users
- Email notifications
- Reports and analytics
- Mobile app version

## Troubleshooting

### Data not saving?
- Check if localStorage is enabled in your browser
- Check browser console for errors
- Make sure JavaScript is enabled

### Can't access GitHub Pages?
- Wait a few minutes after enabling Pages
- Check if the branch and folder are correct
- Make sure the repository is public (or you have GitHub Pro)

### Admin login not working?
- Make sure you're using the default credentials: admin / admin123
- Check browser console for errors
- Clear browser cache and try again

## License

This project is open source and available for use. Feel free to modify and use it for your own purposes.

## Contributing

Feel free to submit issues, fork the repository, and create pull requests for any improvements.

## Support

For issues or questions, please open an issue on the GitHub repository.

