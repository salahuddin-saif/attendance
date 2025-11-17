// utils.js - Utility functions
function formatTime(timeString) {
    if (!timeString) return '-';
    const date = new Date(timeString);
    const hours = date.getHours();
    const minutes = date.getMinutes();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    const displayHours = hours % 12 || 12;
    const displayMinutes = minutes.toString().padStart(2, '0');
    return `${displayHours}:${displayMinutes} ${ampm}`;
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}

function sanitizeInput(input) {
    if (typeof input !== 'string') return '';
    return input.trim().replace(/[<>]/g, '');
}

function showAlert(message, type = 'success') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    // First, try to find alert-container
    let container = document.getElementById('alert-container');
    
    // If not found, try to find a container with main-content
    if (!container) {
        container = document.querySelector('.main-content .container');
        if (container) {
            // Insert at the beginning of the container
            container.insertBefore(alertDiv, container.firstChild);
        }
    } else {
        // Append to alert-container
        container.appendChild(alertDiv);
    }
    
    // Remove alert after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

function exportToCSV(data, filename) {
    if (!data || data.length === 0) {
        showAlert('No data to export', 'error');
        return;
    }

    // Get headers
    const headers = Object.keys(data[0]);
    
    // Create CSV content
    let csv = headers.join(',') + '\n';
    
    data.forEach(row => {
        const values = headers.map(header => {
            const value = row[header] || '';
            // Escape commas and quotes
            if (typeof value === 'string' && (value.includes(',') || value.includes('"'))) {
                return `"${value.replace(/"/g, '""')}"`;
            }
            return value;
        });
        csv += values.join(',') + '\n';
    });

    // Create download link
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function exportAttendanceToCSV(startDate, endDate) {
    const attendance = db.getAttendanceByDateRange(startDate, endDate);
    const employees = db.getEmployees();
    
    // Create date range
    const dateRange = [];
    const start = new Date(startDate);
    const end = new Date(endDate);
    const current = new Date(start);
    
    while (current <= end) {
        dateRange.push(current.toISOString().split('T')[0]);
        current.setDate(current.getDate() + 1);
    }
    
    // Create attendance map
    const attendanceMap = {};
    attendance.forEach(att => {
        const empId = att.employee_id;
        const date = att.check_in.split('T')[0];
        
        if (!attendanceMap[empId]) {
            attendanceMap[empId] = {};
        }
        
        if (!attendanceMap[empId][date]) {
            attendanceMap[empId][date] = formatTime(att.check_in);
        }
    });
    
    // Create CSV data
    const csvData = [];
    employees.forEach(emp => {
        const row = {
            'Employee ID': emp.employee_id,
            'Employee Name': emp.full_name,
            'Department': emp.department,
            'Position': emp.position
        };

        let totalPresent = 0;
        dateRange.forEach(date => {
            const value = attendanceMap[emp.employee_id]?.[date] || '';
            row[date] = value;
            if (value) {
                totalPresent += 1;
            }
        });

        row['Total Days Present'] = totalPresent;
        csvData.push(row);
    });
    
    const filename = `attendance_export_${startDate}_to_${endDate}.csv`;
    exportToCSV(csvData, filename);
    db.logActivity('EXPORT_ATTENDANCE', `Exported attendance records from ${startDate} to ${endDate}`);
}

