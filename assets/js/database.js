// Database.js - LocalStorage-based data storage for GitHub Pages
// This simulates a database using browser localStorage

class Database {
    constructor() {
        this.storageKey = 'attendance_system_db';
        this.dataFilePath = 'data.json';
        this.data = null;
        this.ready = this.initialize();
    }

    getDefaultData() {
        return {
            employees: [],
            attendance: [],
            admin_users: [
                {
                    id: 1,
                    username: 'admin',
                    password: 'admin123', // Plain text for simplicity in static site
                    email: 'admin@company.com'
                }
            ],
            activity_log: []
        };
    }

    async initialize() {
        let data = this.getLocalData();

        if (!this.isValidDataStructure(data)) {
            data = await this.fetchDataFile();
        }

        if (!this.isValidDataStructure(data)) {
            data = this.getDefaultData();
        }

        this.persistLocalData(data);
        this.data = data;
        return this.data;
    }

    onReady(callback) {
        if (typeof callback === 'function') {
            this.ready.then(() => callback(this.getData()));
        }
        return this.ready;
    }

    async fetchDataFile() {
        try {
            const response = await fetch(`${this.dataFilePath}?v=${Date.now()}`, {
                cache: 'no-store'
            });

            if (!response.ok) {
                return null;
            }

            const fileData = await response.json();
            if (this.isValidDataStructure(fileData)) {
                return fileData;
            }
        } catch (error) {
            console.warn('Unable to load data.json file.', error);
        }

        return null;
    }

    async reloadFromDataFile() {
        const fileData = await this.fetchDataFile();
        if (this.isValidDataStructure(fileData)) {
            this.saveData(fileData);
            return fileData;
        }
        return null;
    }

    getLocalData() {
        try {
            const data = localStorage.getItem(this.storageKey);
            return data ? JSON.parse(data) : null;
        } catch (e) {
            console.error('Error reading data:', e);
            return null;
        }
    }

    isValidDataStructure(data) {
        return !!(
            data &&
            typeof data === 'object' &&
            Array.isArray(data.employees) &&
            Array.isArray(data.attendance) &&
            Array.isArray(data.admin_users) &&
            Array.isArray(data.activity_log)
        );
    }

    persistLocalData(data) {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(data));
        } catch (e) {
            console.error('Error saving data locally:', e);
        }
    }

    getData() {
        if (!this.data) {
            const stored = this.getLocalData();
            if (this.isValidDataStructure(stored)) {
                this.data = stored;
            } else {
                this.data = this.getDefaultData();
                this.persistLocalData(this.data);
            }
        }
        return this.data;
    }

    saveData(data) {
        if (!this.isValidDataStructure(data)) {
            console.warn('Attempted to save malformed data payload.');
            return false;
        }

        this.data = data;
        this.persistLocalData(data);
        this.dispatchUpdateEvent();
        return true;
    }

    dispatchUpdateEvent() {
        if (typeof document !== 'undefined' && typeof CustomEvent !== 'undefined') {
            document.dispatchEvent(new CustomEvent('attendance:data-updated', {
                detail: { timestamp: Date.now() }
            }));
        }
    }

    exportToJSON(data) {
        // Create a download link for the JSON data
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'attendance_system_backup.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // Employee operations
    getEmployees() {
        const data = this.getData();
        return data ? data.employees : [];
    }

    getEmployee(employee_id) {
        const employees = this.getEmployees();
        return employees.find(emp => emp.employee_id === employee_id) || null;
    }

    employeeExists(employee_id) {
        return this.getEmployee(employee_id) !== null;
    }

    addEmployee(employee) {
        const data = this.getData();
        if (!data) return false;

        if (this.employeeExists(employee.employee_id)) {
            return false; // Employee already exists
        }

        const newEmployee = {
            id: data.employees.length > 0 
                ? Math.max(...data.employees.map(e => e.id)) + 1 
                : 1,
            employee_id: employee.employee_id,
            full_name: employee.full_name,
            department: employee.department,
            position: employee.position,
            registration_date: new Date().toISOString()
        };

        data.employees.push(newEmployee);
        this.saveData(data);
        this.logActivity('EMPLOYEE_REGISTER', `New employee registered: ${employee.full_name} (${employee.employee_id})`);
        return true;
    }

    updateEmployee(employee_id, updates) {
        const data = this.getData();
        if (!data) return false;

        const index = data.employees.findIndex(emp => emp.employee_id === employee_id);
        if (index === -1) return false;

        data.employees[index] = { ...data.employees[index], ...updates };
        this.saveData(data);
        this.logActivity('EMPLOYEE_UPDATE', `Employee updated: ${updates.full_name || employee_id} (${employee_id})`);
        return true;
    }

    deleteEmployee(employee_id) {
        const data = this.getData();
        if (!data) return false;

        const index = data.employees.findIndex(emp => emp.employee_id === employee_id);
        if (index === -1) return false;

        const employee = data.employees[index];
        data.employees.splice(index, 1);
        
        // Also delete attendance records for this employee
        data.attendance = data.attendance.filter(att => att.employee_id !== employee_id);
        
        this.saveData(data);
        this.logActivity('EMPLOYEE_DELETE', `Employee deleted: ${employee.full_name} (${employee_id})`);
        return true;
    }

    // Attendance operations
    getAttendance() {
        const data = this.getData();
        return data ? data.attendance : [];
    }

    getAttendanceByDateRange(startDate, endDate) {
        const attendance = this.getAttendance();
        return attendance.filter(att => {
            const checkInDate = att.check_in.split('T')[0];
            return checkInDate >= startDate && checkInDate <= endDate;
        });
    }

    getTodaysAttendance() {
        const today = new Date().toISOString().split('T')[0];
        return this.getAttendanceByDateRange(today, today);
    }

    hasAttendanceToday(employee_id) {
        const today = new Date().toISOString().split('T')[0];
        const attendance = this.getAttendance();
        return attendance.some(att => 
            att.employee_id === employee_id && 
            att.check_in.split('T')[0] === today
        );
    }

    hasAttendanceOnDate(employee_id, date) {
        const attendance = this.getAttendance();
        return attendance.some(att => 
            att.employee_id === employee_id && 
            att.check_in.split('T')[0] === date
        );
    }

    markAttendance(employee_id, check_in = null) {
        const data = this.getData();
        if (!data) return false;

        if (!check_in) {
            check_in = new Date().toISOString();
        }

        const newAttendance = {
            id: data.attendance.length > 0 
                ? Math.max(...data.attendance.map(a => a.id)) + 1 
                : 1,
            employee_id: employee_id,
            check_in: check_in,
            check_out: null,
            status: 'present'
        };

        data.attendance.push(newAttendance);
        this.saveData(data);
        
        const employee = this.getEmployee(employee_id);
        const employeeName = employee ? employee.full_name : employee_id;
        this.logActivity('ATTENDANCE_MARKED', `Attendance marked for: ${employeeName} (${employee_id})`);
        return true;
    }

    deleteAttendance(attendance_id) {
        const data = this.getData();
        if (!data) return false;

        const index = data.attendance.findIndex(att => att.id === attendance_id);
        if (index === -1) return false;

        const attendance = data.attendance[index];
        data.attendance.splice(index, 1);
        this.saveData(data);
        
        const employee = this.getEmployee(attendance.employee_id);
        const employeeName = employee ? employee.full_name : attendance.employee_id;
        this.logActivity('ATTENDANCE_DELETE', `Deleted attendance record #${attendance_id} for ${employeeName}`);
        return true;
    }

    getAttendanceStats() {
        const employees = this.getEmployees();
        const today = new Date().toISOString().split('T')[0];
        const todayAttendance = this.getTodaysAttendance();
        const presentToday = new Set(todayAttendance.map(att => att.employee_id)).size;

        // Weekly stats
        const weekStart = new Date();
        weekStart.setDate(weekStart.getDate() - weekStart.getDay());
        const weekStartStr = weekStart.toISOString().split('T')[0];
        const weekEndStr = new Date().toISOString().split('T')[0];
        const weekAttendance = this.getAttendanceByDateRange(weekStartStr, weekEndStr);
        const presentWeek = new Set(weekAttendance.map(att => att.employee_id)).size;

        // Monthly stats
        const monthStart = new Date();
        monthStart.setDate(1);
        const monthStartStr = monthStart.toISOString().split('T')[0];
        const monthEndStr = new Date().toISOString().split('T')[0];
        const monthAttendance = this.getAttendanceByDateRange(monthStartStr, monthEndStr);
        const presentMonth = new Set(monthAttendance.map(att => att.employee_id)).size;

        const totalEmployees = employees.length;
        const absentToday = totalEmployees - presentToday;

        return {
            total_employees: totalEmployees,
            present_today: presentToday,
            absent_today: absentToday,
            attendance_rate_today: totalEmployees > 0 
                ? Math.round((presentToday / totalEmployees) * 100 * 10) / 10 
                : 0,
            present_week: presentWeek,
            attendance_rate_week: totalEmployees > 0 
                ? Math.round((presentWeek / totalEmployees) * 100 * 10) / 10 
                : 0,
            present_month: presentMonth,
            attendance_rate_month: totalEmployees > 0 
                ? Math.round((presentMonth / totalEmployees) * 100 * 10) / 10 
                : 0
        };
    }

    getDepartmentAttendance() {
        const employees = this.getEmployees();
        const today = new Date().toISOString().split('T')[0];
        const todayAttendance = this.getTodaysAttendance();
        const presentEmployeeIds = new Set(todayAttendance.map(att => att.employee_id));

        const departmentMap = {};
        employees.forEach(emp => {
            if (!departmentMap[emp.department]) {
                departmentMap[emp.department] = {
                    department: emp.department,
                    total_employees: 0,
                    present_today: 0
                };
            }
            departmentMap[emp.department].total_employees++;
            if (presentEmployeeIds.has(emp.employee_id)) {
                departmentMap[emp.department].present_today++;
            }
        });

        return Object.values(departmentMap);
    }

    // Admin operations
    validateAdminLogin(username, password) {
        const data = this.getData();
        if (!data) return false;

        const admin = data.admin_users.find(
            user => user.username === username && user.password === password
        );

        if (admin) {
            this.logActivity('ADMIN_LOGIN', `Admin logged in: ${username}`);
            return admin;
        }
        return false;
    }

    // Activity log
    logActivity(action, details) {
        const data = this.getData();
        if (!data) return false;

        const logEntry = {
            id: data.activity_log.length > 0 
                ? Math.max(...data.activity_log.map(l => l.id)) + 1 
                : 1,
            action: action,
            details: details,
            ip_address: 'N/A', // Can't get IP in client-side
            user_agent: navigator.userAgent,
            created_at: new Date().toISOString()
        };

        data.activity_log.push(logEntry);
        this.saveData(data);
        return true;
    }

    // Export data to JSON file
    exportData() {
        const data = this.getData();
        if (data) {
            this.exportToJSON(data);
            return true;
        }
        return false;
    }

    // Import data from JSON file
    importData(jsonData) {
        try {
            const data = typeof jsonData === 'string' ? JSON.parse(jsonData) : jsonData;
            if (data.employees && data.attendance && data.admin_users) {
                this.saveData(data);
                return true;
            }
            return false;
        } catch (e) {
            console.error('Error importing data:', e);
            return false;
        }
    }
}

// Create global database instance
const db = new Database();



